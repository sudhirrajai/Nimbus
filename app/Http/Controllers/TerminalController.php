<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TerminalController extends Controller
{
    private $basePath = '/var/www/';

    /**
     * Blocked commands/patterns for security
     */
    private $blockedPatterns = [
        'rm -rf /',
        'mkfs',
        'dd if=',
        ':(){',    // fork bomb
        '> /dev/sd',
        'chmod -R 777 /',
        'chown -R',
        'shutdown',
        'reboot',
        'halt',
        'poweroff',
        'init 0',
        'init 6',
        'passwd',
        'userdel',
        'useradd',
        'usermod',
        'groupdel',
        'visudo',
        'crontab -r',
    ];

    /**
     * Execute a command in the domain's or scope's directory
     */
    public function execute(Request $request, $domain)
    {
        $request->validate([
            'command' => 'required|string|max:2000',
            'path' => 'nullable|string',
        ]);

        $command = trim($request->input('command'));
        $path = $request->input('path', '');

        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'output' => "Error: Unauthorized.\n",
                'exit_code' => 1,
                'cwd' => $path,
            ], 401);
        }
        $isRoot = $user->isRoot();

        // Resolve scope and base directory
        $normalized = strtolower(trim((string)$domain));
        $isServerRoot = in_array($normalized, ['root', '__root__', 'server']);
        $isProjectsRoot = empty($normalized) || in_array($normalized, ['projects', '__projects__', 'all', 'var_www']);

        if ($isServerRoot) {
            if (!$user->hasFileManagerRootAccess()) {
                return response()->json([
                    'success' => false,
                    'output' => "Error: Access denied. Server root terminal is restricted.\n",
                    'exit_code' => 1,
                    'cwd' => $path,
                ], 403);
            }
            $scopeBasePath = '/';
        } elseif ($isProjectsRoot) {
            if (!$user->hasFileManagerProjectsAccess()) {
                return response()->json([
                    'success' => false,
                    'output' => "Error: Access denied. Web projects terminal is restricted.\n",
                    'exit_code' => 1,
                    'cwd' => $path,
                ], 403);
            }
            $scopeBasePath = '/var/www';
        } else {
            if (!$user->canAccessDomain($domain) && !$user->hasFileManagerProjectsAccess()) {
                return response()->json([
                    'success' => false,
                    'output' => "Error: Access denied to website {$domain}.\n",
                    'exit_code' => 1,
                    'cwd' => $path,
                ], 403);
            }
            $scopeBasePath = '/var/www/' . $domain;
        }

        // Build the working directory
        $cleanPath = ltrim(str_replace(['../', '..\\', './', '.\\'], '', (string)$path), '/');
        $workingDir = rtrim($scopeBasePath, '/');
        if (!empty($cleanPath)) {
            $workingDir .= '/' . $cleanPath;
        }
        if (empty($workingDir)) {
            $workingDir = '/';
        }

        // Validate working directory
        $realBase = realpath($scopeBasePath) ?: $scopeBasePath;
        $realWorkDir = realpath($workingDir);

        if (!$realWorkDir || !is_dir($realWorkDir)) {
            return response()->json([
                'success' => false,
                'output' => "Error: Directory does not exist.\n",
                'exit_code' => 1,
                'cwd' => $path,
            ]);
        }

        if (!$isServerRoot && $realWorkDir !== $realBase && !str_starts_with($realWorkDir, rtrim($realBase, '/') . '/')) {
            return response()->json([
                'success' => false,
                'output' => "Error: Invalid working directory.\n",
                'exit_code' => 1,
                'cwd' => $path,
            ]);
        }

        // Security check: block dangerous commands
        $lowerCmd = strtolower($command);
        foreach ($this->blockedPatterns as $pattern) {
            if (str_contains($lowerCmd, strtolower($pattern))) {
                return response()->json([
                    'success' => false,
                    'output' => "Error: This command is blocked for security reasons.\n",
                    'exit_code' => 1,
                    'cwd' => $path,
                ]);
            }
        }

        // Security check: Jail paths for non-root users
        if (!$isRoot) {
            $isCd = (bool)preg_match('/^cd(?:\s|$)/', $command);
            if (!$this->isCommandSafe($command, $scopeBasePath, $isCd)) {
                return response()->json([
                    'success' => false,
                    'output' => "Error: Access denied. Paths outside your assigned scope or dangerous commands are restricted.\n",
                    'exit_code' => 1,
                    'cwd' => $path,
                ]);
            }
        }

        // Handle 'cd' command specially — parse and update working directory
        if (preg_match('/^cd(?:\s+(.*))?$/', $command, $matches)) {
            $targetDir = isset($matches[1]) ? trim($matches[1]) : '~';
            if ($targetDir === '') {
                $targetDir = '~';
            }

            // Resolve the target directory
            if ($targetDir === '~') {
                $targetDir = $scopeBasePath;
            } elseif (!str_starts_with($targetDir, '/')) {
                $targetDir = $realWorkDir . '/' . $targetDir;
            }

            $realTarget = realpath($targetDir);

            // Must stay within domain/scope root for non-root
            $domainRoot = realpath($scopeBasePath) ?: $scopeBasePath;
            $isWithinRoot = $isServerRoot || ($realTarget && ($realTarget === $domainRoot || str_starts_with($realTarget, rtrim($domainRoot, '/') . '/')));

            if (!$realTarget || !is_dir($realTarget) || (!$isRoot && !$isWithinRoot)) {
                return response()->json([
                    'success' => false,
                    'output' => "bash: cd: {$targetDir}: No such file or directory (or access denied)\n",
                    'exit_code' => 1,
                    'cwd' => $path,
                ]);
            }

            // Compute new relative path from scope root
            if ($realTarget === $domainRoot) {
                $newPath = '';
            } else {
                $newPath = ltrim(substr($realTarget, strlen($domainRoot)), '/');
            }

            $newUser = $this->determineRunUser($realTarget, $domain, $scopeBasePath, $isServerRoot, $isProjectsRoot, $isRoot);

            return response()->json([
                'success' => true,
                'output' => '',
                'exit_code' => 0,
                'cwd' => $newPath,
                'user' => $newUser,
            ]);
        }

        // Handle 'clear' command
        if (trim($command) === 'clear') {
            return response()->json([
                'success' => true,
                'output' => '__CLEAR__',
                'exit_code' => 0,
                'cwd' => $path,
            ]);
        }

        // Determine isolated system user (e.g. site_maharaj_sr or www-data)
        $runUser = $this->determineRunUser($realWorkDir, $domain, $scopeBasePath, $isServerRoot, $isProjectsRoot, $isRoot);

        try {
            Log::info("Terminal command executed", [
                'domain' => $domain,
                'command' => $command,
                'cwd' => $realWorkDir,
                'user' => $runUser,
                'ip' => $request->ip(),
            ]);

            // Auto-append --force to artisan commands that need production confirmation
            if (str_contains($command, 'php artisan')) {
                $forceCommands = ['migrate', 'db:seed', 'db:wipe', 'migrate:fresh', 'migrate:refresh', 'migrate:reset', 'migrate:rollback', 'optimize:clear', 'cache:clear', 'config:clear', 'route:clear', 'view:clear', 'storage:link'];
                $needsForce = false;
                foreach ($forceCommands as $fc) {
                    if (str_contains($command, $fc)) {
                        $needsForce = true;
                        break;
                    }
                }
                if ($needsForce && !str_contains($command, '--force')) {
                    $command .= ' --force';
                } elseif (!$needsForce && !str_contains($command, '--no-interaction')) {
                    $command .= ' --no-interaction';
                }
            }

            // Ensure all binaries in bin/ directories and scripts are executable before build/package commands
            if (preg_match('/\b(npm|yarn|pnpm|npx|vite|composer)\b/i', $command)) {
                \App\Services\SiteIsolationService::ensureExecutables($realWorkDir);
            }

            // Execute the command with a timeout
            $escapedWorkDir = escapeshellarg($realWorkDir);
            $fullCommand = "cd {$escapedWorkDir} && sudo -u " . escapeshellarg($runUser) . " -H bash -c " . escapeshellarg($command) . " 2>&1";

            $descriptors = [
                0 => ['pipe', 'r'],  // stdin
                1 => ['pipe', 'w'],  // stdout
                2 => ['pipe', 'w'],  // stderr
            ];

            $process = proc_open($fullCommand, $descriptors, $pipes, $realWorkDir, [
                'HOME' => $realWorkDir,
                'PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/home/www-data/.config/composer/vendor/bin:' . $realWorkDir . '/node_modules/.bin',
                'TERM' => 'xterm-256color',
            ]);

            if (!is_resource($process)) {
                return response()->json([
                    'success' => false,
                    'output' => "Error: Failed to execute command.\n",
                    'exit_code' => 1,
                    'cwd' => $path,
                ]);
            }

            // Close stdin
            fclose($pipes[0]);

            // Read stdout with timeout
            stream_set_timeout($pipes[1], 30);
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            // Read stderr
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);

            // If an install or update command was executed, ensure any newly unpacked binaries get +x
            if (preg_match('/\b(npm|yarn|pnpm|composer)\s+(install|add|update|require|i)\b/i', $command)) {
                \App\Services\SiteIsolationService::ensureExecutables($realWorkDir);
            }

            $combinedOutput = $output;
            if (!empty($stderr) && empty($output)) {
                $combinedOutput = $stderr;
            }

            // Limit output size to prevent memory issues
            $maxOutput = 100000; // ~100KB
            if (strlen($combinedOutput) > $maxOutput) {
                $combinedOutput = substr($combinedOutput, 0, $maxOutput) . "\n\n... Output truncated (exceeded 100KB) ...\n";
            }

            return response()->json([
                'success' => $exitCode === 0,
                'output' => $combinedOutput,
                'exit_code' => $exitCode,
                'cwd' => $path,
                'user' => $runUser,
            ]);
        } catch (\Exception $e) {
            Log::error("Terminal execution error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'output' => "Error: " . $e->getMessage() . "\n",
                'exit_code' => 1,
                'cwd' => $path,
            ]);
        }
    }

    /**
     * Determine isolated system user for directory execution
     */
    private function determineRunUser($realWorkDir, $domain, $scopeBasePath, $isServerRoot, $isProjectsRoot, $isRoot): string
    {
        if ($isServerRoot && $isRoot) {
            return 'root';
        }

        $targetDomain = null;
        if (!$isServerRoot) {
            if (!$isProjectsRoot) {
                $targetDomain = $domain;
            } else {
                // In projects scope: if workdir is within /var/www/{siteDomain}, extract {siteDomain}
                $rel = ltrim(substr($realWorkDir, strlen($scopeBasePath)), '/');
                if (!empty($rel)) {
                    $parts = explode('/', $rel);
                    $targetDomain = $parts[0];
                }
            }
        }

        if ($targetDomain) {
            $siteUser = \App\Services\SiteIsolationService::siteUser($targetDomain);
            $userCheck = @exec("id -u " . escapeshellarg($siteUser) . " 2>/dev/null");
            if (!empty($userCheck) && is_numeric($userCheck)) {
                return $siteUser;
            }
        }

        return 'www-data';
    }

    /**
     * Check if the command is safe to execute based on paths and patterns
     */
    private function isCommandSafe($command, $scopeRoot, $allowDots = false)
    {
        $domainRoot = realpath($scopeRoot) ?: $scopeRoot;
        if (!$domainRoot) return false;

        // Block dangerous commands for non-root users
        $dangerous = ['sudo', 'su', 'chroot', 'passwd', 'userdel', 'useradd', 'visudo', 'apt', 'yum', 'dnf'];
        foreach ($dangerous as $d) {
            if (str_contains(strtolower($command), $d)) return false;
        }

        // Split command to tokens and check paths
        $tokens = preg_split('/\s+/', $command);
        foreach ($tokens as $token) {
            $token = trim($token, "\"'");
            
            // Block directory traversal unless it's a 'cd' command (handled specially)
            if (!$allowDots && str_contains($token, '..')) return false;
            
            // Block absolute paths that don't start with the allowed root
            if (str_starts_with($token, '/') && !str_starts_with($token, rtrim($domainRoot, '/'))) {
                return false;
            }
        }

        return true;
    }
}
