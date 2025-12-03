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
            $showHidden = $request->input('showHidden', false);
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
                $name = basename($dir);
                
                // Skip hidden files if not requested
                if (!$showHidden && str_starts_with($name, '.')) {
                    continue;
                }
                
                $items[] = [
                    'name' => $name,
                    'type' => 'directory',
                    'size' => $this->getDirectorySize($dir),
                    'modified' => date('Y-m-d H:i:s', File::lastModified($dir)),
                    'permissions' => substr(sprintf('%o', fileperms($dir)), -4),
                    'hidden' => str_starts_with($name, '.')
                ];
            }

            // Add files
            foreach ($files as $file) {
                $name = basename($file);
                
                // Skip hidden files if not requested
                if (!$showHidden && str_starts_with($name, '.')) {
                    continue;
                }
                
                $items[] = [
                    'name' => $name,
                    'type' => 'file',
                    'extension' => File::extension($file),
                    'size' => File::size($file),
                    'sizeFormatted' => $this->formatBytes(File::size($file)),
                    'modified' => date('Y-m-d H:i:s', File::lastModified($file)),
                    'permissions' => substr(sprintf('%o', fileperms($file)), -4),
                    'editable' => $this->isTextFile($file),
                    'hidden' => str_starts_with($name, '.')
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
                'path' => 'nullable|string',
                'name' => 'required|string',
                'permissions' => 'required|string|regex:/^[0-7]{3,4}$/',
                'recursive' => 'boolean'
            ]);

            $path = $request->input('path', '');
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

            $escapedPath = escapeshellarg($targetPath);
            
            if ($recursive && File::isDirectory($targetPath)) {
                $this->executeSudoCommand("chmod -R {$permissions} {$escapedPath}");
            } else {
                $this->executeSudoCommand("chmod {$permissions} {$escapedPath}");
            }

            return response()->json(['message' => 'Permissions changed successfully']);

        } catch (\Exception $e) {
            \Log::error("Chmod error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete file or directory
     */
    public function delete(Request $request, $domain)
    {
        try {
            $request->validate([
                'path' => 'nullable|string',
                'name' => 'required|string'
            ]);

            $path = $request->input('path', '');
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
            $this->executeSudoCommand("rm -rf {$escapedPath}");

            return response()->json(['message' => 'Deleted successfully']);

        } catch (\Exception $e) {
            \Log::error("Delete error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete multiple items
     */
    public function deleteMultiple(Request $request, $domain)
    {
        try {
            $request->validate([
                'path' => 'nullable|string',
                'items' => 'required|array',
                'items.*' => 'string'
            ]);

            $path = $request->input('path', '');
            $items = $request->input('items');
            $dirPath = $this->getFullPath($domain, $path);

            foreach ($items as $item) {
                $targetPath = $dirPath . '/' . $item;
                
                if (!$this->isValidPath($targetPath)) {
                    continue;
                }

                if (File::exists($targetPath)) {
                    $escapedPath = escapeshellarg($targetPath);
                    $this->executeSudoCommand("rm -rf {$escapedPath}");
                }
            }

            return response()->json(['message' => 'Items deleted successfully']);

        } catch (\Exception $e) {
            \Log::error("Multiple delete error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Rename file or directory
     */
    public function rename(Request $request, $domain)
    {
        try {
            $request->validate([
                'path' => 'nullable|string',
                'oldName' => 'required|string',
                'newName' => 'required|string|max:255'
            ]);

            $path = $request->input('path', '');
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
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Copy file or directory
     */
    public function copy(Request $request, $domain)
    {
        try {
            $request->validate([
                'sourcePath' => 'nullable|string',
                'name' => 'required|string',
                'destinationPath' => 'nullable|string'
            ]);

            $sourcePath = $request->input('sourcePath', '');
            $name = $request->input('name');
            $destPath = $request->input('destinationPath', '');

            $sourceDir = $this->getFullPath($domain, $sourcePath);
            $destDir = $this->getFullPath($domain, $destPath);
            
            $sourceFull = $sourceDir . '/' . $name;
            $destFull = $destDir . '/' . $name;

            if (!$this->isValidPath($sourceFull) || !$this->isValidPath($destFull)) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            if (!File::exists($sourceFull)) {
                return response()->json(['error' => 'Source not found'], 404);
            }

            if (!File::exists($destDir)) {
                $this->executeSudoCommand("mkdir -p " . escapeshellarg($destDir));
            }

            $escapedSource = escapeshellarg($sourceFull);
            $escapedDest = escapeshellarg($destFull);
            $this->executeSudoCommand("cp -r {$escapedSource} {$escapedDest}");
            $this->executeSudoCommand("chown -R www-data:www-data {$escapedDest}");

            return response()->json(['message' => 'Copied successfully']);

        } catch (\Exception $e) {
            \Log::error("Copy error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Move file or directory
     */
    public function move(Request $request, $domain)
    {
        try {
            $request->validate([
                'sourcePath' => 'nullable|string',
                'name' => 'required|string',
                'destinationPath' => 'nullable|string'
            ]);

            $sourcePath = $request->input('sourcePath', '');
            $name = $request->input('name');
            $destPath = $request->input('destinationPath', '');

            $sourceDir = $this->getFullPath($domain, $sourcePath);
            $destDir = $this->getFullPath($domain, $destPath);
            
            $sourceFull = $sourceDir . '/' . $name;
            $destFull = $destDir . '/' . $name;

            if (!$this->isValidPath($sourceFull) || !$this->isValidPath($destFull)) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            if (!File::exists($sourceFull)) {
                return response()->json(['error' => 'Source not found'], 404);
            }

            if (!File::exists($destDir)) {
                $this->executeSudoCommand("mkdir -p " . escapeshellarg($destDir));
            }

            $escapedSource = escapeshellarg($sourceFull);
            $escapedDest = escapeshellarg($destFull);
            $this->executeSudoCommand("mv {$escapedSource} {$escapedDest}");

            return response()->json(['message' => 'Moved successfully']);

        } catch (\Exception $e) {
            \Log::error("Move error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Create ZIP archive
     */
    public function zip(Request $request, $domain)
    {
        try {
            $request->validate([
                'path' => 'nullable|string',
                'items' => 'required|array',
                'items.*' => 'string',
                'zipName' => 'required|string|max:255'
            ]);

            $path = $request->input('path', '');
            $items = $request->input('items');
            $zipName = $request->input('zipName');

            if (!str_ends_with($zipName, '.zip')) {
                $zipName .= '.zip';
            }

            $dirPath = $this->getFullPath($domain, $path);
            $zipPath = $dirPath . '/' . $zipName;

            if (!$this->isValidPath($zipPath)) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            // Build file list for zip command
            $fileList = array_map('escapeshellarg', $items);
            $filesString = implode(' ', $fileList);
            $escapedZipPath = escapeshellarg($zipPath);
            $escapedDirPath = escapeshellarg($dirPath);

            // Create zip using system command
            $this->executeSudoCommand("cd {$escapedDirPath} && zip -r {$escapedZipPath} {$filesString}");
            $this->executeSudoCommand("chown www-data:www-data {$escapedZipPath}");
            $this->executeSudoCommand("chmod 644 {$escapedZipPath}");

            return response()->json(['message' => 'ZIP archive created successfully']);

        } catch (\Exception $e) {
            \Log::error("ZIP error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
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

            if (!File::exists($dirPath)) {
                $this->executeSudoCommand("mkdir -p " . escapeshellarg($dirPath));
                $this->executeSudoCommand("chown -R www-data:www-data " . escapeshellarg($dirPath));
            }

            File::put($filePath, '');
            
            $escapedFilePath = escapeshellarg($filePath);
            $this->executeSudoCommand("chown www-data:www-data {$escapedFilePath}");
            $this->executeSudoCommand("chmod 644 {$escapedFilePath}");

            return response()->json(['message' => 'File created successfully']);

        } catch (\Exception $e) {
            \Log::error("File create error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
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

            if (!File::exists($dirPath)) {
                $this->executeSudoCommand("mkdir -p " . escapeshellarg($dirPath));
                $this->executeSudoCommand("chown -R www-data:www-data " . escapeshellarg($dirPath));
            }

            $this->executeSudoCommand("mkdir -p " . escapeshellarg($newDirPath));
            $this->executeSudoCommand("chown -R www-data:www-data " . escapeshellarg($newDirPath));

            return response()->json(['message' => 'Directory created successfully']);

        } catch (\Exception $e) {
            \Log::error("Directory create error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
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
                'file' => 'required|file|max:102400'
            ]);

            $path = $request->input('path', '');
            $file = $request->file('file');
            $dirPath = $this->getFullPath($domain, $path);
            $targetPath = $dirPath . '/' . $file->getClientOriginalName();

            if (!$this->isValidPath($targetPath)) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            if (!File::exists($dirPath)) {
                $this->executeSudoCommand("mkdir -p " . escapeshellarg($dirPath));
                $this->executeSudoCommand("chown -R www-data:www-data " . escapeshellarg($dirPath));
            }

            $file->move($dirPath, $file->getClientOriginalName());
            
            $escapedTargetPath = escapeshellarg($targetPath);
            $this->executeSudoCommand("chown www-data:www-data {$escapedTargetPath}");
            $this->executeSudoCommand("chmod 644 {$escapedTargetPath}");

            return response()->json(['message' => 'File uploaded successfully']);

        } catch (\Exception $e) {
            \Log::error("Upload error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
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
        
        return strpos($realPath, $baseRealPath) === 0;
    }

    private function isTextFile($file)
    {
        $textExtensions = [
            'txt', 'php', 'html', 'htm', 'css', 'js', 'json', 'xml', 
            'md', 'yml', 'yaml', 'ini', 'conf', 'sh', 'env', 'log',
            'sql', 'py', 'java', 'c', 'cpp', 'h', 'vue', 'jsx', 'ts',
            'htaccess', 'gitignore', 'editorconfig', 'eslintrc', 'prettierrc'
        ];

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $basename = basename($file);
        
        // Check if it's a dotfile that's text-editable
        if (str_starts_with($basename, '.')) {
            $textDotFiles = ['.htaccess', '.env', '.gitignore', '.editorconfig', 
                            '.eslintrc', '.prettierrc', '.babelrc'];
            if (in_array($basename, $textDotFiles)) {
                return true;
            }
        }
        
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
        try {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                $size += $file->getSize();
            }
        } catch (\Exception $e) {
            \Log::warning("Failed to calculate directory size: " . $e->getMessage());
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