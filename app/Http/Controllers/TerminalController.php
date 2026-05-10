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
     * Execute a command in the domain's directory
     */
    public function execute(Request $request, $domain)
    {
        $request->validate([
            'command' => 'required|string|max:2000',
            'path' => 'nullable|string',
        ]);

        $command = trim($request->input('command'));
        $path = $request->input('path', '');

        // Build the working directory
        $workingDir = rtrim($this->basePath . $domain, '/');
        if (!empty($path)) {
            $workingDir .= '/' . ltrim($path, '/');
        }

        // Validate working directory
        $realBase = realpath($this->basePath);
        $realWorkDir = realpath($workingDir);

        if (!$realBase || !$realWorkDir || !str_starts_with($realWorkDir, $realBase)) {
            return response()->json([
                'success' => false,
                'output' => "Error: Invalid working directory.\n",
                'exit_code' => 1,
                'cwd' => $path,
            ]);
        }

        if (!is_dir($realWorkDir)) {
            return response()->json([
                'success' => false,
                'output' => "Error: Directory does not exist.\n",
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
        $user = auth()->user();
        $isRoot = $user && $user->role === 'root';

        if (!$isRoot) {
            if (!$this->isCommandSafe($command, $domain)) {
                return response()->json([
                    'success' => false,
                    'output' => "Error: Access denied. Paths outside your domain or dangerous commands are restricted.\n",
                    'exit_code' => 1,
                    'cwd' => $path,
                ]);
            }
        }

        // Handle 'cd' command specially — parse and update working directory
        if (preg_match('/^cd\s+(.+)$/', $command, $matches)) {
            $targetDir = trim($matches[1]);

            // Resolve the target directory
            if ($targetDir === '~') {
                $targetDir = $this->basePath . $domain;
            } elseif (!str_starts_with($targetDir, '/')) {
                $targetDir = $realWorkDir . '/' . $targetDir;
            }

            $realTarget = realpath($targetDir);

            // Must stay within domain root for non-root
            $domainRoot = realpath($this->basePath . $domain);
            if (!$realTarget || !is_dir($realTarget) || (!$isRoot && !str_starts_with($realTarget, $domainRoot))) {
                return response()->json([
                    'success' => false,
                    'output' => "bash: cd: {$matches[1]}: No such file or directory (or access denied)\n",
                    'exit_code' => 1,
                    'cwd' => $path,
                ]);
            }

            // Compute new relative path from domain root
            $domainRoot = realpath($this->basePath . $domain);
            if ($realTarget === $domainRoot) {
                $newPath = '';
            } else {
                $newPath = ltrim(substr($realTarget, strlen($domainRoot)), '/');
            }

            return response()->json([
                'success' => true,
                'output' => '',
                'exit_code' => 0,
                'cwd' => $newPath,
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

        try {
            Log::info("Terminal command executed", [
                'domain' => $domain,
                'command' => $command,
                'cwd' => $realWorkDir,
                'ip' => $request->ip(),
            ]);

            // Execute the command with a timeout
            $escapedWorkDir = escapeshellarg($realWorkDir);
            $fullCommand = "cd {$escapedWorkDir} && sudo -u www-data bash -c " . escapeshellarg($command) . " 2>&1";

            $descriptors = [
                0 => ['pipe', 'r'],  // stdin
                1 => ['pipe', 'w'],  // stdout
                2 => ['pipe', 'w'],  // stderr
            ];

            $process = proc_open($fullCommand, $descriptors, $pipes, $realWorkDir, [
                'HOME' => '/tmp',
                'PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
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
     * Check if the command is safe to execute based on paths and patterns
     */
    private function isCommandSafe($command, $domain)
    {
        $domainRoot = realpath($this->basePath . $domain);
        if (!$domainRoot) return false;

        // Block dangerous commands for non-root users
        $dangerous = ['sudo', 'su', 'chroot', 'passwd', 'userdel', 'useradd', 'visudo'];
        foreach ($dangerous as $d) {
            if (str_contains(strtolower($command), $d)) return false;
        }

        // Split command to tokens and check paths
        $tokens = preg_split('/\s+/', $command);
        foreach ($tokens as $token) {
            $token = trim($token, "\"'");
            
            // Block directory traversal
            if (str_contains($token, '..')) return false;
            
            // Block absolute paths that don't start with the domain root
            if (str_starts_with($token, '/') && !str_starts_with($token, $domainRoot)) {
                return false;
            }
        }

        return true;
    }
}
