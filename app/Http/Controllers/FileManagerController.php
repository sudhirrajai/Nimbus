<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class FileManagerController extends Controller
{
    private $basePath = '/var/www/';
    private $gitSystemUser = 'www-data';

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
            $showHidden = filter_var($request->input('showHidden', false), FILTER_VALIDATE_BOOLEAN);
            $fullPath = $this->getFullPath($domain, $path);

            if (!$this->isValidPath($fullPath)) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            if (!File::exists($fullPath) || !is_dir($fullPath)) {
                return response()->json(['error' => 'Path not found'], 404);
            }

            $items = [];

            // Use scandir so we can control whether hidden files are included
            $entries = @scandir($fullPath);
            if ($entries === false) {
                return response()->json(['error' => 'Failed to read directory'], 500);
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

            if (!$this->isValidPath($archivePath) || !$this->isValidPath($destPath)) {
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
                $this->executeSudoCommand("unzip -o {$escapedArchive} -d {$escapedDest}");
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
                'file' => 'required|file|max:512000' // 500MB max
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

    /**
     * Get Git status for the current file manager path.
     */
    public function gitStatus(Request $request, $domain)
    {
        try {
            $path = $request->input('path', '');
            $fullPath = $this->getFullPath($domain, $path);

            if (!$this->isValidPath($fullPath)) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $repoPath = $this->resolveGitRepository($domain, $fullPath);

            if (!$repoPath) {
                return response()->json([
                    'available' => false,
                    'message' => 'No Git repository found in this folder or its parent folders.'
                ]);
            }

            $branch = trim($this->executeGitCommand($repoPath, ['branch', '--show-current'])[0] ?? '');
            $statusLines = $this->executeGitCommand($repoPath, ['status', '--short', '--branch']);
            $branchLines = $this->executeGitCommand($repoPath, ['for-each-ref', '--format=%(refname:short)', 'refs/heads']);
            $stashLines = $this->executeGitCommand($repoPath, ['stash', 'list']);

            $cleanStatusLines = array_values(array_filter(array_map('trim', $statusLines)));
            $branches = array_values(array_filter(array_map('trim', $branchLines)));
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

            return response()->json([
                'available' => true,
                'repoRoot' => $this->toDomainRelativePath($domain, $repoPath),
                'branch' => $branch,
                'branches' => $branches,
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
                'action' => 'required|string|in:pull,push,commit,switch_branch,stash,stash_pop',
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

            if (!$this->isValidPath($fullPath)) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $repoPath = $this->resolveGitRepository($domain, $fullPath);

            if (!$repoPath) {
                return response()->json(['error' => 'No Git repository found for this path.'], 404);
            }

            $output = [];

            switch ($action) {
                case 'pull':
                    $currentBranch = trim($this->executeGitCommand($repoPath, ['branch', '--show-current'])[0] ?? '');
                    $output = $this->executeGitCommand($repoPath, ['pull', '--ff-only', 'origin', $currentBranch]);
                    break;

                case 'push':
                    $currentBranch = trim($this->executeGitCommand($repoPath, ['branch', '--show-current'])[0] ?? '');
                    $output = $this->executeGitCommand($repoPath, ['push', 'origin', $currentBranch]);
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

                    $output = $this->executeGitCommand($repoPath, ['switch', $branch]);
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

            $domainPath = $this->basePath . $domain;
            if (!$this->isValidPath($domainPath)) {
                return response()->json(['error' => 'Access denied'], 403);
            }

            $tokenPath = $domainPath . '/.git-token';
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
        $domainPath = $this->basePath . $domain;
        $tokenPath = $domainPath . '/.git-token';

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
            'ts',
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

    private function resolveGitRepository($domain, $fullPath)
    {
        $domainPath = realpath($this->basePath . $domain);
        $searchPath = File::isDirectory($fullPath) ? $fullPath : dirname($fullPath);
        $realSearchPath = realpath($searchPath);

        if (!$domainPath || !$realSearchPath || strpos($realSearchPath, $domainPath) !== 0) {
            return null;
        }

        $currentPath = $realSearchPath;

        while ($currentPath && strpos($currentPath, $domainPath) === 0) {
            if (File::exists($currentPath . DIRECTORY_SEPARATOR . '.git')) {
                return $currentPath;
            }

            if ($currentPath === $domainPath) {
                break;
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
        $domainRoot = realpath($this->basePath . $domain);
        $realPath = realpath($fullPath);

        if (!$domainRoot || !$realPath) {
            return '';
        }

        $relative = ltrim(substr($realPath, strlen($domainRoot)), DIRECTORY_SEPARATOR);
        return str_replace(DIRECTORY_SEPARATOR, '/', $relative);
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
                // Use a credential helper that returns the token for HTTPS auth
                $envParts[] = 'GIT_ASKPASS=/bin/echo';
                // Set up a one-time credential helper
                $credentialHelper = "credential.helper=!f() { echo username=x-access-token; echo password={$token}; }; f";
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

        if ($tokenExists && isset($credentialHelper)) {
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
