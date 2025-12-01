<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class FileManagerController extends Controller
{
    private $basePath = '/var/www/';

    /**
     * Display file manager for a domain
     */
    public function index($domain)
    {
        $domainPath = $this->basePath . $domain;

        // Security check
        if (!$this->isValidPath($domainPath)) {
            abort(403, 'Access denied');
        }

        if (!File::exists($domainPath)) {
            return redirect()->route('domains.list');
        }

        return Inertia::render('Files/FileManager', [
            'domain' => $domain,
            'initialPath' => ''
        ]);
    }

    /**
     * List files and directories
     */
    public function list(Request $request, $domain)
    {
        try {
            $path = $request->input('path', '');
            $fullPath = $this->getFullPath($domain, $path);

            if (!$this->isValidPath($fullPath)) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            if (!File::exists($fullPath)) {
                return response()->json(['error' => 'Path not found'], 404);
            }

            $items = [];
            $files = File::files($fullPath);
            $directories = File::directories($fullPath);

            // Add directories first
            foreach ($directories as $dir) {
                $items[] = [
                    'name' => basename($dir),
                    'type' => 'directory',
                    'size' => $this->getDirectorySize($dir),
                    'modified' => date('Y-m-d H:i:s', File::lastModified($dir)),
                    'permissions' => substr(sprintf('%o', fileperms($dir)), -4)
                ];
            }

            // Add files
            foreach ($files as $file) {
                $items[] = [
                    'name' => basename($file),
                    'type' => 'file',
                    'extension' => File::extension($file),
                    'size' => File::size($file),
                    'sizeFormatted' => $this->formatBytes(File::size($file)),
                    'modified' => date('Y-m-d H:i:s', File::lastModified($file)),
                    'permissions' => substr(sprintf('%o', fileperms($file)), -4),
                    'editable' => $this->isTextFile($file)
                ];
            }

            return response()->json([
                'items' => $items,
                'currentPath' => $path,
                'breadcrumbs' => $this->getBreadcrumbs($path)
            ]);

        } catch (\Exception $e) {
            \Log::error("File list error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to list files'], 500);
        }
    }

    /**
     * Change file or directory permissions
     */
    public function chmod(Request $request, $domain)
    {
        try {
            $request->validate([
                'path' => 'required|string',
                'name' => 'required|string',
                'permissions' => 'required|string|regex:/^[0-7]{3,4}$/',
                'recursive' => 'boolean'
            ]);

            $path = $request->input('path');
            $name = $request->input('name');
            $permissions = $request->input('permissions');
            $recursive = $request->input('recursive', false);
            
            $dirPath = $this->getFullPath($domain, $path);
            $targetPath = $dirPath . '/' . $name;

            if (!$this->isValidPath($targetPath)) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            if (!File::exists($targetPath)) {
                return response()->json(['error' => 'File or directory not found'], 404);
            }

            // Escape paths for security
            $escapedPath = escapeshellarg($targetPath);
            
            if ($recursive && File::isDirectory($targetPath)) {
                // Change permissions recursively
                $this->executeSudoCommand("chmod -R {$permissions} {$escapedPath}");
            } else {
                // Change permissions for single file/directory
                $this->executeSudoCommand("chmod {$permissions} {$escapedPath}");
            }

            return response()->json(['message' => 'Permissions changed successfully']);

        } catch (\Exception $e) {
            \Log::error("Chmod error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to change permissions'], 500);
        }
    }

    /**
     * Read file content
     */
    public function read(Request $request, $domain)
    {
        try {
            $path = $request->input('path');
            $fullPath = $this->getFullPath($domain, $path);

            if (!$this->isValidPath($fullPath)) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            if (!File::exists($fullPath) || !File::isFile($fullPath)) {
                return response()->json(['error' => 'File not found'], 404);
            }

            if (!$this->isTextFile($fullPath)) {
                return response()->json(['error' => 'File is not editable'], 400);
            }

            $content = File::get($fullPath);

            return response()->json([
                'content' => $content,
                'name' => basename($fullPath),
                'size' => File::size($fullPath)
            ]);

        } catch (\Exception $e) {
            \Log::error("File read error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to read file'], 500);
        }
    }

    /**
     * Save file content
     */
    public function save(Request $request, $domain)
    {
        try {
            $request->validate([
                'path' => 'required|string',
                'content' => 'required|string'
            ]);

            $path = $request->input('path');
            $content = $request->input('content');
            $fullPath = $this->getFullPath($domain, $path);

            if (!$this->isValidPath($fullPath)) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            if (!$this->isTextFile($fullPath)) {
                return response()->json(['error' => 'File is not editable'], 400);
            }

            File::put($fullPath, $content);

            return response()->json([
                'message' => 'File saved successfully',
                'size' => File::size($fullPath)
            ]);

        } catch (\Exception $e) {
            \Log::error("File save error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to save file'], 500);
        }
    }

    /**
     * Create new file
     */
    public function createFile(Request $request, $domain)
    {
        try {
            $request->validate([
                'path' => 'nullable|string',
                'name' => 'required|string|max:255'
            ]);

            $path = $request->input('path', '');
            $name = $request->input('name');
            $dirPath = $this->getFullPath($domain, $path);
            $filePath = $dirPath . '/' . $name;

            if (!$this->isValidPath($filePath)) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            if (File::exists($filePath)) {
                return response()->json(['error' => 'File already exists'], 409);
            }

            // Ensure directory exists and has proper permissions
            if (!File::exists($dirPath)) {
                File::makeDirectory($dirPath, 0755, true);
                $this->executeSudoCommand("chown -R www-data:www-data " . escapeshellarg($dirPath));
            }

            // Create the file
            File::put($filePath, '');
            
            // Set proper ownership and permissions
            $escapedFilePath = escapeshellarg($filePath);
            $this->executeSudoCommand("chown www-data:www-data {$escapedFilePath}");
            $this->executeSudoCommand("chmod 644 {$escapedFilePath}");

            return response()->json(['message' => 'File created successfully']);

        } catch (\Exception $e) {
            \Log::error("File create error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to create file: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Create new directory
     */
    public function createDirectory(Request $request, $domain)
    {
        try {
            $request->validate([
                'path' => 'nullable|string',
                'name' => 'required|string|max:255'
            ]);

            $path = $request->input('path', '');
            $name = $request->input('name');
            $dirPath = $this->getFullPath($domain, $path);
            $newDirPath = $dirPath . '/' . $name;

            if (!$this->isValidPath($newDirPath)) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            if (File::exists($newDirPath)) {
                return response()->json(['error' => 'Directory already exists'], 409);
            }

            // Ensure parent directory exists
            if (!File::exists($dirPath)) {
                File::makeDirectory($dirPath, 0755, true);
                $this->executeSudoCommand("chown -R www-data:www-data " . escapeshellarg($dirPath));
            }

            // Create directory
            File::makeDirectory($newDirPath, 0755, true);
            
            // Set proper ownership
            $escapedNewDirPath = escapeshellarg($newDirPath);
            $this->executeSudoCommand("chown -R www-data:www-data {$escapedNewDirPath}");

            return response()->json(['message' => 'Directory created successfully']);

        } catch (\Exception $e) {
            \Log::error("Directory create error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to create directory: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete file or directory
     */
    public function delete(Request $request, $domain)
    {
        try {
            $request->validate([
                'path' => 'required|string',
                'name' => 'required|string'
            ]);

            $path = $request->input('path');
            $name = $request->input('name');
            $dirPath = $this->getFullPath($domain, $path);
            $targetPath = $dirPath . '/' . $name;

            if (!$this->isValidPath($targetPath)) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            if (!File::exists($targetPath)) {
                return response()->json(['error' => 'File or directory not found'], 404);
            }

            $escapedPath = escapeshellarg($targetPath);

            if (File::isDirectory($targetPath)) {
                $this->executeSudoCommand("rm -rf {$escapedPath}");
            } else {
                $this->executeSudoCommand("rm -f {$escapedPath}");
            }

            return response()->json(['message' => 'Deleted successfully']);

        } catch (\Exception $e) {
            \Log::error("Delete error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to delete'], 500);
        }
    }

    /**
     * Rename file or directory
     */
    public function rename(Request $request, $domain)
    {
        try {
            $request->validate([
                'path' => 'required|string',
                'oldName' => 'required|string',
                'newName' => 'required|string|max:255'
            ]);

            $path = $request->input('path');
            $oldName = $request->input('oldName');
            $newName = $request->input('newName');
            
            $dirPath = $this->getFullPath($domain, $path);
            $oldPath = $dirPath . '/' . $oldName;
            $newPath = $dirPath . '/' . $newName;

            if (!$this->isValidPath($oldPath) || !$this->isValidPath($newPath)) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            if (!File::exists($oldPath)) {
                return response()->json(['error' => 'File or directory not found'], 404);
            }

            if (File::exists($newPath)) {
                return response()->json(['error' => 'Target name already exists'], 409);
            }

            $escapedOldPath = escapeshellarg($oldPath);
            $escapedNewPath = escapeshellarg($newPath);
            $this->executeSudoCommand("mv {$escapedOldPath} {$escapedNewPath}");

            return response()->json(['message' => 'Renamed successfully']);

        } catch (\Exception $e) {
            \Log::error("Rename error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to rename'], 500);
        }
    }

    /**
     * Upload file
     */
    public function upload(Request $request, $domain)
    {
        try {
            $request->validate([
                'path' => 'nullable|string',
                'file' => 'required|file|max:102400' // 100MB max
            ]);

            $path = $request->input('path', '');
            $file = $request->file('file');
            $dirPath = $this->getFullPath($domain, $path);
            $targetPath = $dirPath . '/' . $file->getClientOriginalName();

            if (!$this->isValidPath($targetPath)) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            // Ensure directory exists
            if (!File::exists($dirPath)) {
                File::makeDirectory($dirPath, 0755, true);
                $this->executeSudoCommand("chown -R www-data:www-data " . escapeshellarg($dirPath));
            }

            // Move uploaded file
            $file->move($dirPath, $file->getClientOriginalName());
            
            // Set proper ownership and permissions
            $escapedTargetPath = escapeshellarg($targetPath);
            $this->executeSudoCommand("chown www-data:www-data {$escapedTargetPath}");
            $this->executeSudoCommand("chmod 644 {$escapedTargetPath}");

            return response()->json(['message' => 'File uploaded successfully']);

        } catch (\Exception $e) {
            \Log::error("Upload error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to upload file: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Download file
     */
    public function download(Request $request, $domain)
    {
        try {
            $path = $request->input('path');
            $fullPath = $this->getFullPath($domain, $path);

            if (!$this->isValidPath($fullPath)) {
                abort(403, 'Access denied');
            }

            if (!File::exists($fullPath) || !File::isFile($fullPath)) {
                abort(404, 'File not found');
            }

            return response()->download($fullPath);

        } catch (\Exception $e) {
            \Log::error("Download error: " . $e->getMessage());
            abort(500, 'Failed to download file');
        }
    }

    // Helper methods

    private function getFullPath($domain, $path = '')
    {
        $domainPath = $this->basePath . $domain;
        if (empty($path)) {
            return $domainPath;
        }
        return $domainPath . '/' . ltrim($path, '/');
    }

    private function isValidPath($path)
    {
        $realPath = realpath($path) ?: $path;
        $baseRealPath = realpath($this->basePath);
        
        // Ensure path is within base path
        return strpos($realPath, $baseRealPath) === 0;
    }

    private function isTextFile($file)
    {
        $textExtensions = [
            'txt', 'php', 'html', 'htm', 'css', 'js', 'json', 'xml', 
            'md', 'yml', 'yaml', 'ini', 'conf', 'sh', 'env', 'log',
            'sql', 'py', 'java', 'c', 'cpp', 'h', 'vue', 'jsx', 'ts'
        ];

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        return in_array($extension, $textExtensions);
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    private function getDirectorySize($path)
    {
        $size = 0;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            $size += $file->getSize();
        }

        return $this->formatBytes($size);
    }

    private function getBreadcrumbs($path)
    {
        if (empty($path)) {
            return [['name' => 'Root', 'path' => '']];
        }

        $parts = explode('/', trim($path, '/'));
        $breadcrumbs = [['name' => 'Root', 'path' => '']];
        $currentPath = '';

        foreach ($parts as $part) {
            $currentPath .= '/' . $part;
            $breadcrumbs[] = [
                'name' => $part,
                'path' => ltrim($currentPath, '/')
            ];
        }

        return $breadcrumbs;
    }

    private function executeSudoCommand($command)
    {
        $output = [];
        $returnCode = 0;
        
        \Log::debug("Executing sudo command: sudo $command");
        exec("sudo $command 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            $errorMsg = "Command execution failed: " . implode("\n", $output);
            \Log::error($errorMsg);
            throw new \Exception($errorMsg);
        }
        
        return $output;
    }
}