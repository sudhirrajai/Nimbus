<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ShieldController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class FileManagerController extends Controller
{
    private $basePath = '/var/www/';
    private $gitSystemUser = 'www-data';

    /**
     * Display file manager for a scope or domain
     */
    public function index(Request $request, $domain = null)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('auth.login');
        }

        // If no domain provided, default based on user's permission
        if (!$domain) {
            if ($user->hasFileManagerProjectsAccess()) {
                $domain = 'projects';
            } else {
                $accessible = $user->accessibleDomains();
                $domain = !empty($accessible) ? $accessible[0] : null;
                if (!$domain) {
                    return redirect()->route('domains.list')->with('error', 'No website assigned to your account.');
                }
            }
        }

        $scopeInfo = $this->resolveScopeInfo($request, $domain);
        $domainPath = $scopeInfo['basePath'];

        // Security check
        if (!$this->isValidPath($domain, $domainPath)) {
            abort(403, 'Access denied');
        }

        if (!File::exists($domainPath)) {
            if ($scopeInfo['scope'] === 'domain') {
                return redirect()->route('domains.list');
            }
        }

        // Available domains list for quick switching dropdown
        $domains = [];
        if (File::exists('/var/www')) {
            $dirs = glob('/var/www/*', GLOB_ONLYDIR);
            foreach ($dirs as $dir) {
                $name = basename($dir);
                if (!in_array($name, ['html', 'default', 'nimbus'])) {
                    if ($user->canAccessDomain($name) || $user->hasFileManagerProjectsAccess()) {
                        $domains[] = $name;
                    }
                }
            }
        }

        return Inertia::render('Files/FileManager', [
            'domain' => $scopeInfo['domainParam'],
            'scope' => $scopeInfo['scope'],
            'displayScope' => $scopeInfo['displayScope'],
            'initialPath' => $request->query('path', ''),
            'userScope' => $user->getFileManagerScope(),
            'allowedScopes' => $user->getAllowedFileManagerScopes(),
            'availableDomains' => $domains,
        ]);
    }

    /**
     * List files and directories
     */
    public function list(Request $request, $domain)
    {
        try {
            $path = $request->input('path', '');
            $showHidden = filter_var($request->input('showHidden', false), FILTER_VALIDATE_BOOLEAN);
            $fullPath = $this->getFullPath($domain, $path);

            if (!$this->isValidPath($domain, $fullPath)) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            if (!File::exists($fullPath) || !is_dir($fullPath)) {
                return response()->json(['error' => 'Path not found'], 404);
            }

            $items = [];

            // Use scandir so we can control whether hidden files are included
            $entries = @scandir($fullPath);
            if ($entries === false) {
                // Fallback to sudo ls
                $escapedPath = escapeshellarg($fullPath);
                $output = $this->executeSudoCommand("ls -A1 {$escapedPath}");
                if (empty($output)) {
                    $entries = [];
                } else {
                    $entries = array_filter(array_map('trim', $output));
                }
            }

            // Separate directories and files so directories come first
            $dirs = [];
            $files = [];

            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                // Skip hidden if not requested
                if (!$showHidden && str_starts_with($entry, '.')) {
                    continue;
                }

                $entryPath = $fullPath . DIRECTORY_SEPARATOR . $entry;
                if (is_dir($entryPath)) {
                    $dirs[] = $entry;
                } elseif (is_file($entryPath)) {
                    $files[] = $entry;
                }
            }

            // Directories
            foreach ($dirs as $name) {
                $dir = $fullPath . DIRECTORY_SEPARATOR . $name;
                $items[] = [
                    'name' => $name,
                    'type' => 'directory',
                    'size' => $this->getDirectorySize($dir),
                    'modified' => date('Y-m-d H:i:s', filemtime($dir)),
                    'permissions' => substr(sprintf('%o', fileperms($dir)), -4),
                    'hidden' => str_starts_with($name, '.')
                ];
            }

            // Files
            foreach ($files as $name) {
                $file = $fullPath . DIRECTORY_SEPARATOR . $name;
                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $items[] = [
                    'name' => $name,
                    'type' => 'file',
                    'extension' => $extension,
                    'size' => filesize($file),
                    'sizeFormatted' => $this->formatBytes(filesize($file)),
                    'modified' => date('Y-m-d H:i:s', filemtime($file)),
                    'permissions' => substr(sprintf('%o', fileperms($file)), -4),
                    'editable' => $this->isTextFile($file),
                    'hidden' => str_starts_with($name, '.')
                ];
            }

            return response()->json([
                'items' => $items,
                'currentPath' => $path,
                'breadcrumbs' => $this->getBreadcrumbs($path, $domain)
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

            if (!$this->isValidPath($domain, $targetPath)) {
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

            if (!$this->isValidPath($domain, $targetPath)) {
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

                if (!$this->isValidPath($domain, $targetPath)) {
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

            if (!$this->isValidPath($domain, $oldPath) || !$this->isValidPath($domain, $newPath)) {
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

            if (!$this->isValidPath($domain, $sourceFull) || !$this->isValidPath($domain, $destFull)) {
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

            if (!$this->isValidPath($domain, $sourceFull) || !$this->isValidPath($domain, $destFull)) {
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

            if (!$this->isValidPath($domain, $zipPath)) {
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
     * Extract ZIP or tar.gz archive
     */
    public function extract(Request $request, $domain)
    {
        try {
            $request->validate([
                'path' => 'nullable|string',
                'name' => 'required|string',
                'destination' => 'nullable|string'
            ]);

            $path = $request->input('path', '');
            $name = $request->input('name');
            $destination = $request->input('destination', '');

            $dirPath = $this->getFullPath($domain, $path);
            $archivePath = $dirPath . '/' . $name;

            // If no destination specified, extract to same directory
            if (empty($destination)) {
                $destPath = $dirPath;
            } else {
                $destPath = $this->getFullPath($domain, $destination);
            }

            if (!$this->isValidPath($domain, $archivePath) || !$this->isValidPath($domain, $destPath)) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            if (!File::exists($archivePath)) {
                return response()->json(['error' => 'Archive not found'], 404);
            }

            // Create destination directory if it doesn't exist
            if (!File::exists($destPath)) {
                $this->executeSudoCommand("mkdir -p " . escapeshellarg($destPath));
            }

            $escapedArchive = escapeshellarg($archivePath);
            $escapedDest = escapeshellarg($destPath);
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            // Determine extraction command based on file type
            if ($extension === 'zip') {
                if (class_exists('ZipArchive')) {
                    $zip = new \ZipArchive;
                    $res = $zip->open($archivePath);
                    if ($res === TRUE) {
                        $zip->extractTo($destPath);
                        $zip->close();
                    } else {
                        throw new \Exception("Could not open ZIP archive. Error code: " . $res);
                    }
                } else {
                    // Fallback to system unzip if extension is missing
                    $this->executeSudoCommand("unzip -o {$escapedArchive} -d {$escapedDest}");
                }
            } elseif ($extension === 'gz' || str_ends_with(strtolower($name), '.tar.gz')) {
                $this->executeSudoCommand("tar -xzf {$escapedArchive} -C {$escapedDest}");
            } elseif ($extension === 'tar') {
                $this->executeSudoCommand("tar -xf {$escapedArchive} -C {$escapedDest}");
            } else {
                return response()->json(['error' => 'Unsupported archive format. Supported: zip, tar, tar.gz'], 400);
            }

            // Set proper ownership
            $this->executeSudoCommand("chown -R www-data:www-data {$escapedDest}");

            return response()->json([
                'message' => 'Archive extracted successfully',
                'destination' => $destination ?: $path
            ]);
        } catch (\Exception $e) {
            \Log::error("Extract error: " . $e->getMessage());
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

            if (!$this->isValidPath($domain, $fullPath)) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            if (!File::exists($fullPath) || !File::isFile($fullPath)) {
                return response()->json(['error' => 'File not found'], 404);
            }

            if (!$this->isTextFile($fullPath)) {
                return response()->json(['error' => 'File is not editable'], 400);
            }

            // Try reading with standard PHP first, fall back to sudo cat for protected files
            $content = '';
            try {
                $content = File::get($fullPath);
            } catch (\Exception $e) {
                // Fallback to sudo cat
                $escapedPath = escapeshellarg($fullPath);
                $output = $this->executeSudoCommand("cat {$escapedPath}");
                $content = implode("\n", $output);
            }

            return response()->json([
                'content' => $content,
                'name' => basename($fullPath),
                'size' => File::size($fullPath)
            ]);
        } catch (\Exception $e) {
            \Log::error("File read error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to read file: ' . $e->getMessage()], 500);
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
                'content' => 'present|string|nullable'
            ]);

            $path = $request->input('path');
            $content = $request->input('content') ?? '';
            $fullPath = $this->getFullPath($domain, $path);

            if (!$this->isValidPath($domain, $fullPath)) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            if (!$this->isTextFile($fullPath)) {
                return response()->json(['error' => 'File is not editable'], 400);
            }

            try {
                File::put($fullPath, $content);
            } catch (\Exception $e) {
                // Fallback: write to temp and sudo mv
                $tempPath = tempnam(sys_get_temp_dir(), 'nimbus_save_');
                File::put($tempPath, $content);
                
                $escapedTemp = escapeshellarg($tempPath);
                $escapedFull = escapeshellarg($fullPath);
                
                $this->executeSudoCommand("mv {$escapedTemp} {$escapedFull}");
                $this->executeSudoCommand("chown www-data:www-data {$escapedFull}");
                $this->executeSudoCommand("chmod 644 {$escapedFull}");
            }

            return response()->json([
                'message' => 'File saved successfully',
                'size' => File::size($fullPath)
            ]);
        } catch (\Exception $e) {
            \Log::error("File save error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to save file: ' . $e->getMessage()], 500);
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

            if (!$this->isValidPath($domain, $filePath)) {
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

            if (!$this->isValidPath($domain, $newDirPath)) {
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

    public function upload(Request $request, $domain)
    {
        try {
            $request->validate([
                'path' => 'nullable|string',
                'file' => 'required|file',
                'isChunk' => 'nullable|string',
                'chunkIndex' => 'nullable|integer',
                'totalChunks' => 'nullable|integer',
                'originalName' => 'nullable|string'
            ]);

            $path = $request->input('path', '');
            $file = $request->file('file');
            $dirPath = $this->getFullPath($domain, $path);
            
            $isChunk = filter_var($request->input('isChunk', false), FILTER_VALIDATE_BOOLEAN);

            if (!File::exists($dirPath)) {
                $this->executeSudoCommand("mkdir -p " . escapeshellarg($dirPath));
                $this->executeSudoCommand("chown -R www-data:www-data " . escapeshellarg($dirPath));
            }

            if ($isChunk) {
                $originalName = $request->input('originalName');
                $chunkIndex = $request->input('chunkIndex');
                $totalChunks = $request->input('totalChunks');
                $targetPath = $dirPath . '/' . $originalName;
                $tempFilePath = $dirPath . '/' . $originalName . '.part';

                if (!$this->isValidPath($domain, $targetPath)) {
                    return response()->json(['error' => 'Access denied'], 403);
                }

                // Delete any orphaned part file if this is the start of a new upload
                if ($chunkIndex == 0 && File::exists($tempFilePath)) {
                    File::delete($tempFilePath);
                }

                // Append chunk to temp file
                $chunkData = file_get_contents($file->getRealPath());
                file_put_contents($tempFilePath, $chunkData, FILE_APPEND);

                // Ensure ownership of temp file on first chunk
                if ($chunkIndex == 0) {
                    $escapedTemp = escapeshellarg($tempFilePath);
                    $this->executeSudoCommand("chown www-data:www-data {$escapedTemp}");
                }

                // If last chunk, rename to original file
                if ($chunkIndex == $totalChunks - 1) {
                    $escapedTemp = escapeshellarg($tempFilePath);
                    $escapedTarget = escapeshellarg($targetPath);
                    $this->executeSudoCommand("mv {$escapedTemp} {$escapedTarget}");
                    $this->executeSudoCommand("chmod 644 {$escapedTarget}");

                    // Nimbus Shield: Scan on upload (Asynchronous background scan for raw performance)
                    $escapedTarget = escapeshellarg($targetPath);
                    $cmd = "php artisan shield:scan-file {$escapedTarget}";
                    exec("nohup {$cmd} > /dev/null 2>&1 &");

                    return response()->json(['message' => 'File uploaded successfully']);
                }

                return response()->json(['message' => 'Chunk uploaded successfully']);
            } else {
                // Fallback for single non-chunked uploads
                $targetPath = $dirPath . '/' . $file->getClientOriginalName();

                if (!$this->isValidPath($domain, $targetPath)) {
                    return response()->json(['error' => 'Access denied'], 403);
                }

                $file->move($dirPath, $file->getClientOriginalName());

                $escapedTargetPath = escapeshellarg($targetPath);
                $this->executeSudoCommand("chown www-data:www-data {$escapedTargetPath}");
                $this->executeSudoCommand("chmod 644 {$escapedTargetPath}");

                // Nimbus Shield: Scan on upload (Asynchronous background scan for raw performance)
                $escapedTarget = escapeshellarg($targetPath);
                $cmd = "php artisan shield:scan-file {$escapedTarget}";
                exec("nohup {$cmd} > /dev/null 2>&1 &");

                return response()->json(['message' => 'File uploaded successfully']);
            }
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

            if (!$this->isValidPath($domain, $fullPath)) {
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

    /**
     * Get Git status for the current file manager path.
     */
    public function gitStatus(Request $request, $domain)
    {
        try {
            $path = $request->input('path', '');
            $fullPath = $this->getFullPath($domain, $path);

            if (!$this->isValidPath($domain, $fullPath)) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $repoPath = $this->resolveGitRepository($domain, $fullPath);

            if (!$repoPath) {
                return response()->json([
                    'available' => false,
                    'message' => 'No Git repository found in this folder or its parent folders.'
                ]);
            }

            // Ensure remote tracking and credentials format are auto-configured
            $this->ensureRemoteConfigured($repoPath, $domain);

            $branch = trim($this->executeGitCommand($repoPath, ['branch', '--show-current'])[0] ?? '');
            $statusLines = $this->executeGitCommand($repoPath, ['status', '--short', '--branch']);
            $localBranchLines = $this->executeGitCommand($repoPath, ['for-each-ref', '--format=%(refname:short)', 'refs/heads']);
            $remoteBranchLines = [];
            try {
                $remoteBranchLines = $this->executeGitCommand($repoPath, ['for-each-ref', '--format=%(refname:short)', 'refs/remotes/origin']);
            } catch (\Exception $e) {
                $remoteBranchLines = [];
            }
            $stashLines = $this->executeGitCommand($repoPath, ['stash', 'list']);

            $cleanStatusLines = array_values(array_filter(array_map('trim', $statusLines)));
            $localBranches = array_values(array_filter(array_map('trim', $localBranchLines)));
            
            // Clean remote branches: strip 'origin/' prefix and remove 'origin/HEAD'
            $remoteBranches = [];
            foreach ($remoteBranchLines as $rb) {
                $rb = trim($rb);
                if (empty($rb) || str_contains($rb, 'HEAD')) continue;
                $cleaned = preg_replace('/^origin\//', '', $rb);
                if (!empty($cleaned) && !in_array($cleaned, $remoteBranches)) {
                    $remoteBranches[] = $cleaned;
                }
            }

            // Build unified branch structure
            $allBranchNames = array_unique(array_merge($localBranches, $remoteBranches));
            $allBranches = [];
            foreach ($allBranchNames as $bName) {
                $allBranches[] = [
                    'name' => $bName,
                    'isCurrent' => $bName === $branch,
                    'isLocal' => in_array($bName, $localBranches),
                    'isRemote' => in_array($bName, $remoteBranches),
                ];
            }

            // Get last commit info
            $lastCommit = null;
            try {
                $logOutput = $this->executeGitCommand($repoPath, ['log', '-1', '--pretty=format:%h|%an|%cr|%s']);
                if (!empty($logOutput) && !empty($logOutput[0])) {
                    $parts = explode('|', $logOutput[0], 4);
                    $lastCommit = [
                        'hash' => $parts[0] ?? '',
                        'author' => $parts[1] ?? '',
                        'date' => $parts[2] ?? '',
                        'subject' => $parts[3] ?? '',
                    ];
                }
            } catch (\Exception $e) {
                // Ignore log error
            }

            $stashes = array_map(function ($line) {
                if (preg_match('/^(stash@\{\d+\}):(.*)$/', $line, $matches)) {
                    return [
                        'ref' => trim($matches[1]),
                        'message' => trim($matches[2]),
                    ];
                }

                return [
                    'ref' => trim($line),
                    'message' => trim($line),
                ];
            }, array_values(array_filter(array_map('trim', $stashLines))));

            // Check if token exists
            $tokenPath = $repoPath . '/.git-token';
            if (!file_exists($tokenPath)) {
                $scopeInfo = $this->resolveScopeInfo(request(), $domain);
                $tokenPath = rtrim($scopeInfo['basePath'], '/') . '/.git-token';
            }
            $tokenExists = false;
            $tokenOutput = [];
            exec("sudo test -f " . escapeshellarg($tokenPath) . " && echo 'exists'", $tokenOutput);
            $tokenExists = !empty($tokenOutput) && trim($tokenOutput[0]) === 'exists';

            return response()->json([
                'available' => true,
                'repoRoot' => $this->toDomainRelativePath($domain, $repoPath),
                'branch' => $branch,
                'branches' => $localBranches,
                'remoteBranches' => $remoteBranches,
                'allBranches' => $allBranches,
                'lastCommit' => $lastCommit,
                'hasToken' => $tokenExists,
                'statusLines' => $cleanStatusLines,
                'dirty' => count(array_filter($cleanStatusLines, fn ($line) => !str_starts_with($line, '##'))) > 0,
                'stashes' => $stashes,
            ]);
        } catch (\Exception $e) {
            \Log::error("Git status error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Run a fixed Git action for the current repository.
     */
    public function gitAction(Request $request, $domain)
    {
        try {
            $request->validate([
                'path' => 'nullable|string',
                'action' => 'required|string|in:pull,push,commit,switch_branch,create_branch,fetch,stash,stash_pop',
                'message' => 'nullable|string|max:500',
                'branch' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9._\/-]+$/'],
                'stash' => ['nullable', 'string', 'max:100', 'regex:/^stash@\{\d+\}$/'],
            ]);

            $path = $request->input('path', '');
            $action = $request->input('action');
            $message = trim((string) $request->input('message', ''));
            $branch = trim((string) $request->input('branch', ''));
            $stash = trim((string) $request->input('stash', ''));
            $fullPath = $this->getFullPath($domain, $path);

            if (!$this->isValidPath($domain, $fullPath)) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $repoPath = $this->resolveGitRepository($domain, $fullPath);

            if (!$repoPath) {
                return response()->json(['error' => 'No Git repository found for this path.'], 404);
            }

            $output = [];

            // Auto-heal remote tracking and credential URL format
            $this->ensureRemoteConfigured($repoPath, $domain);

            switch ($action) {
                case 'fetch':
                    $output = $this->executeGitCommand($repoPath, ['fetch', '--all', '--prune']);
                    if (empty($output)) {
                        $output = ['All remote branches fetched and up to date.'];
                    }
                    break;

                case 'pull':
                    $currentBranch = trim($this->executeGitCommand($repoPath, ['branch', '--show-current'])[0] ?? '');
                    if (empty($currentBranch)) {
                        return response()->json(['error' => 'Cannot pull: repository is in a detached HEAD state.'], 422);
                    }
                    $output = $this->executeGitCommand($repoPath, ['pull', 'origin', $currentBranch]);
                    break;

                case 'push':
                    $currentBranch = trim($this->executeGitCommand($repoPath, ['branch', '--show-current'])[0] ?? '');
                    if (empty($currentBranch)) {
                        return response()->json(['error' => 'Cannot push: repository is in a detached HEAD state.'], 422);
                    }
                    $output = $this->executeGitCommand($repoPath, ['push', '-u', 'origin', $currentBranch]);
                    break;

                case 'commit':
                    if ($message === '') {
                        return response()->json(['error' => 'Commit message is required.'], 422);
                    }

                    $status = $this->executeGitCommand($repoPath, ['status', '--porcelain']);
                    if (count(array_filter(array_map('trim', $status))) === 0) {
                        return response()->json(['error' => 'There are no changes to commit.'], 422);
                    }

                    $this->executeGitCommand($repoPath, ['add', '-A']);
                    $output = $this->executeGitCommand($repoPath, ['commit', '-m', $message]);
                    break;

                case 'switch_branch':
                    if ($branch === '') {
                        return response()->json(['error' => 'Branch name is required.'], 422);
                    }

                    // Check if local branch exists
                    $localBranches = array_map('trim', $this->executeGitCommand($repoPath, ['for-each-ref', '--format=%(refname:short)', 'refs/heads']));
                    
                    if (in_array($branch, $localBranches)) {
                        $output = $this->executeGitCommand($repoPath, ['checkout', $branch]);
                    } else {
                        // Switch to and track remote branch
                        $output = $this->executeGitCommand($repoPath, ['checkout', '-B', $branch, "origin/{$branch}"]);
                    }
                    break;

                case 'create_branch':
                    if ($branch === '') {
                        return response()->json(['error' => 'New branch name is required.'], 422);
                    }

                    $output = $this->executeGitCommand($repoPath, ['checkout', '-b', $branch]);
                    break;

                case 'stash':
                    $output = $message !== ''
                        ? $this->executeGitCommand($repoPath, ['stash', 'push', '-m', $message])
                        : $this->executeGitCommand($repoPath, ['stash', 'push']);
                    break;

                case 'stash_pop':
                    $output = $stash !== ''
                        ? $this->executeGitCommand($repoPath, ['stash', 'pop', $stash])
                        : $this->executeGitCommand($repoPath, ['stash', 'pop']);
                    break;
            }

            return response()->json([
                'message' => 'Git action completed successfully.',
                'output' => implode("\n", $output),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->validator->errors()->first()], 422);
        } catch (\Exception $e) {
            \Log::error("Git action error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Save a Git personal access token for a domain.
     * Stored in /var/www/{domain}/.git-token with restricted permissions.
     */
    public function saveGitToken(Request $request, $domain)
    {
        try {
            $request->validate([
                'token' => 'required|string|max:500',
            ]);

            $scopeInfo = $this->resolveScopeInfo($request, $domain);
            $domainPath = $scopeInfo['basePath'];
            if (!$this->isValidPath($domain, $domainPath)) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $tokenPath = rtrim($domainPath, '/') . '/.git-token';
            $token = trim($request->input('token'));

            // Write the token file with sudo for proper permissions
            $escapedPath = escapeshellarg($tokenPath);
            $escapedToken = escapeshellarg($token);
            $this->executeSudoCommand("bash -c 'echo {$escapedToken} > {$escapedPath}'");
            $this->executeSudoCommand("chmod 600 {$escapedPath}");
            $this->executeSudoCommand("chown root:root {$escapedPath}");

            return response()->json(['message' => 'Git token saved successfully']);
        } catch (\Exception $e) {
            \Log::error("Save git token error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get whether a Git token exists for a domain (never returns the actual token).
     */
    public function getGitToken(Request $request, $domain)
    {
        $scopeInfo = $this->resolveScopeInfo($request, $domain);
        $domainPath = $scopeInfo['basePath'];
        $tokenPath = rtrim($domainPath, '/') . '/.git-token';

        // Check if token file exists using sudo since it's owned by root
        $output = [];
        $returnCode = 0;
        exec("sudo test -f " . escapeshellarg($tokenPath) . " && echo 'exists'", $output, $returnCode);

        $exists = !empty($output) && trim($output[0]) === 'exists';

        return response()->json([
            'hasToken' => $exists,
        ]);
    }

    // Helper methods

    /**
     * Resolve the base root directory and effective scope for the current request
     */
    private function resolveScopeInfo(?Request $request = null, $domain = null): array
    {
        $user = ($request ?: request())->user();
        $normalized = strtolower(trim((string)$domain));

        // 1. Server Root Scope (/)
        if (in_array($normalized, ['root', '__root__', 'server'])) {
            if ($user && !$user->hasFileManagerRootAccess()) {
                abort(403, 'Permission denied. Server root access is restricted.');
            }
            return [
                'scope' => 'root',
                'basePath' => '/',
                'displayScope' => 'Server Root (/)',
                'isServerRoot' => true,
                'isProjectsRoot' => false,
                'domainParam' => 'root',
                'domain' => null,
            ];
        }

        // 2. Web Projects Root Scope (/var/www)
        if (empty($normalized) || in_array($normalized, ['projects', '__projects__', 'all', 'var_www'])) {
            if ($user && !$user->hasFileManagerProjectsAccess()) {
                // If user doesn't have projects access, fallback to first accessible domain
                $accessible = $user->accessibleDomains();
                if (!empty($accessible)) {
                    $domain = $accessible[0];
                    return [
                        'scope' => 'domain',
                        'basePath' => '/var/www/' . $domain,
                        'displayScope' => $domain,
                        'isServerRoot' => false,
                        'isProjectsRoot' => false,
                        'domainParam' => $domain,
                        'domain' => $domain,
                    ];
                }
                abort(403, 'Permission denied. Web projects access is restricted.');
            }

            return [
                'scope' => 'projects',
                'basePath' => '/var/www',
                'displayScope' => 'Web Projects (/var/www)',
                'isServerRoot' => false,
                'isProjectsRoot' => true,
                'domainParam' => 'projects',
                'domain' => null,
            ];
        }

        // 3. Domain Scoped (/var/www/{domain})
        return [
            'scope' => 'domain',
            'basePath' => '/var/www/' . $domain,
            'displayScope' => $domain,
            'isServerRoot' => false,
            'isProjectsRoot' => false,
            'domainParam' => $domain,
            'domain' => $domain,
        ];
    }

    private function getFullPath($domain, $path = '')
    {
        $scopeInfo = $this->resolveScopeInfo(request(), $domain);
        $base = rtrim($scopeInfo['basePath'], '/');
        $cleanPath = ltrim(str_replace(['../', '..\\', './', '.\\'], '', (string)$path), '/');

        if (empty($cleanPath)) {
            return empty($base) ? '/' : $base;
        }

        return (empty($base) ? '' : $base) . '/' . $cleanPath;
    }

    private function isValidPath($domain, $path)
    {
        // Sanitize path by removing any '..' or './' sequences manually first
        $cleanPath = str_replace(['../', '..\\', './', '.\\'], '', (string)$path);
        $scopeInfo = $this->resolveScopeInfo(request(), $domain);

        // If Server Root (/): Any clean path is permitted
        if ($scopeInfo['isServerRoot']) {
            return true;
        }

        $allowedBase = realpath($scopeInfo['basePath']) ?: $scopeInfo['basePath'];
        $realPath = realpath($cleanPath);

        // If realpath failed (file doesn't exist yet), check parent directory
        if (!$realPath) {
            $parentDir = realpath(dirname($cleanPath));
            if (!$parentDir) return false;
            return strpos($parentDir, $allowedBase) === 0;
        }

        return strpos($realPath, $allowedBase) === 0;
    }

    private function isTextFile($file)
    {
        $textExtensions = [
            'txt',
            'php',
            'html',
            'htm',
            'css',
            'js',
            'json',
            'xml',
            'md',
            'yml',
            'yaml',
            'ini',
            'conf',
            'sh',
            'env',
            'log',
            'sql',
            'py',
            'java',
            'c',
            'cpp',
            'h',
            'vue',
            'jsx',
            'tsx',
            'ts',
            'sass',
            'scss',
            'less',
            'blade.php',
            'htaccess',
            'gitignore',
            'editorconfig',
            'eslintrc',
            'prettierrc'
        ];

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $basename = basename($file);

        // Check if it's a dotfile that's text-editable
        if (str_starts_with($basename, '.')) {
            $textDotFiles = [
                '.htaccess',
                '.env',
                '.gitignore',
                '.editorconfig',
                '.eslintrc',
                '.prettierrc',
                '.babelrc'
            ];
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

    private function getBreadcrumbs($path, $domain = null)
    {
        $scopeInfo = $this->resolveScopeInfo(request(), $domain);
        $rootName = $scopeInfo['displayScope'] ?: 'Root';

        if (empty($path)) {
            return [['name' => $rootName, 'path' => '']];
        }

        $parts = explode('/', trim($path, '/'));
        $breadcrumbs = [['name' => $rootName, 'path' => '']];
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

    private function resolveGitRepository($domain, $fullPath)
    {
        $searchPath = File::isDirectory($fullPath) ? $fullPath : dirname($fullPath);
        $realSearchPath = realpath($searchPath);

        if (!$realSearchPath) {
            return null;
        }

        $currentPath = $realSearchPath;
        while ($currentPath && $currentPath !== '/' && strlen($currentPath) > 1) {
            if (File::exists($currentPath . DIRECTORY_SEPARATOR . '.git')) {
                return $currentPath;
            }

            $parentPath = dirname($currentPath);
            if ($parentPath === $currentPath) {
                break;
            }

            $currentPath = $parentPath;
        }

        return null;
    }

    private function toDomainRelativePath($domain, $fullPath)
    {
        $scopeInfo = $this->resolveScopeInfo(request(), $domain);
        $root = realpath($scopeInfo['basePath']) ?: $scopeInfo['basePath'];
        $realPath = realpath($fullPath) ?: $fullPath;

        if (!$realPath) {
            return '';
        }

        if ($root === '/') {
            return ltrim($realPath, '/');
        }

        if (strlen($realPath) >= strlen($root)) {
            $relative = ltrim(substr($realPath, strlen($root)), DIRECTORY_SEPARATOR);
            return str_replace(DIRECTORY_SEPARATOR, '/', $relative);
        }

        return ltrim($realPath, '/');
    }

    private function ensureRemoteConfigured($repoPath, $domain)
    {
        try {
            $remotes = $this->executeGitCommand($repoPath, ['remote']);
            if (in_array('origin', array_map('trim', $remotes))) {
                // 1. Ensure all branches are fetched (fixes --single-branch restriction)
                $this->executeGitCommand($repoPath, ['config', 'remote.origin.fetch', '+refs/heads/*:refs/remotes/origin/*']);

                // 2. Fix URL if token was saved as bare username without x-access-token:
                $remoteUrlOutput = $this->executeGitCommand($repoPath, ['remote', 'get-url', 'origin']);
                $remoteUrl = trim($remoteUrlOutput[0] ?? '');

                if ($remoteUrl && preg_match('/^https:\/\/([^@:]+)@(github\.com|gitlab\.com|bitbucket\.org)(.*)$/i', $remoteUrl, $matches)) {
                    $userPart = $matches[1];
                    $host = $matches[2];
                    $rest = $matches[3];
                    // If it is a personal token (ghp_..., github_pat_..., glpat-...) or token without colon password
                    if (str_starts_with($userPart, 'ghp_') || str_starts_with($userPart, 'github_pat_') || str_starts_with($userPart, 'glpat-') || strlen($userPart) > 20) {
                        $fixedUrl = "https://x-access-token:{$userPart}@{$host}{$rest}";
                        $this->executeGitCommand($repoPath, ['remote', 'set-url', 'origin', $fixedUrl]);
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore config errors
        }
    }

    private function executeGitCommand($repoPath, array $arguments)
    {
        $output = [];
        $returnCode = 0;
        $escapedRepoPath = escapeshellarg($repoPath);
        $escapedSafeDirectory = escapeshellarg("safe.directory={$repoPath}");
        $escapedArguments = implode(' ', array_map('escapeshellarg', $arguments));

        // Detect the domain from the repo path to find the .git-token file
        $domainRoot = $repoPath;
        $domainBase = realpath($this->basePath);
        if ($domainBase && strpos($repoPath, $domainBase) === 0) {
            $relative = ltrim(substr($repoPath, strlen($domainBase)), '/');
            $domainName = explode('/', $relative)[0] ?? '';
            $domainRoot = $domainBase . '/' . $domainName;
        }

        $tokenPath = $domainRoot . '/.git-token';
        $envParts = ['HOME=/tmp', 'GIT_TERMINAL_PROMPT=0'];

        // Check if a .git-token file exists — use it for HTTPS credential auth
        $tokenExists = false;
        $checkOutput = [];
        exec("sudo test -f " . escapeshellarg($tokenPath) . " && echo 'yes'", $checkOutput);
        if (!empty($checkOutput) && trim($checkOutput[0]) === 'yes') {
            $tokenExists = true;
            // Read the token securely
            $tokenOutput = [];
            exec("sudo cat " . escapeshellarg($tokenPath), $tokenOutput);
            $token = trim(implode('', $tokenOutput));
            if ($token) {
                // Set up credential helper that provides token for any username
                $credentialHelper = "credential.helper=!f() { echo password={$token}; echo username=x-access-token; }; f";
                $authHeader = base64_encode("x-access-token:{$token}");
                $extraHeader = "http.extraheader=AUTHORIZATION: basic {$authHeader}";
            }
        }

        // Check if SSH key exists as fallback
        if (!$tokenExists) {
            $sshKeyCheck = [];
            exec("sudo test -f /root/.ssh/id_rsa && echo 'yes'", $sshKeyCheck);
            if (!empty($sshKeyCheck) && trim($sshKeyCheck[0]) === 'yes') {
                $envParts[] = 'GIT_SSH_COMMAND="ssh -i /root/.ssh/id_rsa -o StrictHostKeyChecking=no"';
            }
        }

        $envString = implode(' ', $envParts);

        if ($tokenExists && isset($credentialHelper) && isset($extraHeader)) {
            $command = "sudo env {$envString} git -c " . escapeshellarg($extraHeader) . " -c " . escapeshellarg($credentialHelper) . " -c {$escapedSafeDirectory} -C {$escapedRepoPath} {$escapedArguments}";
        } elseif ($tokenExists && isset($credentialHelper)) {
            $command = "sudo env {$envString} git -c " . escapeshellarg($credentialHelper) . " -c {$escapedSafeDirectory} -C {$escapedRepoPath} {$escapedArguments}";
        } else {
            $command = "sudo env {$envString} git -c {$escapedSafeDirectory} -C {$escapedRepoPath} {$escapedArguments}";
        }

        \Log::debug("Executing git command: {$command}");
        exec("{$command} 2>&1", $output, $returnCode);

        if ($returnCode !== 0) {
            $errorMsg = trim(implode("\n", $output)) ?: 'Git command failed.';
            \Log::error($errorMsg);
            throw new \Exception($errorMsg);
        }

        return $output;
    }

    /**
     * Search for files or content within files
     */
    public function search(Request $request, $domain)
    {
        try {
            $request->validate([
                'query' => 'required|string|min:2',
                'path' => 'nullable|string',
                'type' => 'required|string|in:filename,content'
            ]);

            $query = $request->input('query');
            $path = $request->input('path', '');
            $type = $request->input('type');
            $fullPath = $this->getFullPath($domain, $path);

            if (!$this->isValidPath($domain, $fullPath)) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $results = [];
            $escapedPath = escapeshellarg($fullPath);
            $escapedQuery = escapeshellarg($query);

            if ($type === 'filename') {
                // Search for filenames using 'find'
                $output = [];
                $command = "sudo find {$escapedPath} -maxdepth 5 -name " . escapeshellarg("*{$query}*") . " -not -path '*/node_modules/*' -not -path '*/.git/*'";
                exec($command, $output);

                foreach ($output as $line) {
                    if (empty($line)) continue;
                    $relPath = $this->toDomainRelativePath($domain, $line);
                    $results[] = [
                        'name' => basename($line),
                        'path' => $relPath,
                        'fullPath' => $line,
                        'type' => is_dir($line) ? 'directory' : 'file',
                        'matchType' => 'filename',
                        'editable' => is_dir($line) ? false : $this->isTextFile($line)
                    ];
                }
            } else {
                // Search for content using 'grep'
                $output = [];
                // -r: recursive, -l: list files only, -i: case-insensitive
                $command = "sudo grep -ril --exclude-dir={node_modules,.git} {$escapedQuery} {$escapedPath} | head -n 50";
                exec($command, $output);

                foreach ($output as $line) {
                    if (empty($line)) continue;
                    $relPath = $this->toDomainRelativePath($domain, $line);
                    $results[] = [
                        'name' => basename($line),
                        'path' => $relPath,
                        'fullPath' => $line,
                        'type' => 'file',
                        'matchType' => 'content',
                        'editable' => $this->isTextFile($line)
                    ];
                }
            }

            return response()->json([
                'results' => array_slice($results, 0, 100),
                'query' => $query
            ]);
        } catch (\Exception $e) {
            \Log::error("Search error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
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
