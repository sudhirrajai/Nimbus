<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class Pm2Controller extends Controller
{
    private string $pm2Home = '/root/.pm2';
    private string $basePath = '/var/www/';

    /**
     * Display PM2 Manager page
     */
    public function index()
    {
        return Inertia::render('PM2/Index');
    }

    /**
     * Check PM2, Node.js, and npm installation status
     */
    public function getStatus()
    {
        try {
            $nodePath = trim(shell_exec('which node 2>/dev/null') ?: '');
            $npmPath = trim(shell_exec('which npm 2>/dev/null') ?: '');
            $pm2Path = trim(shell_exec('which pm2 2>/dev/null') ?: '');

            $nodeVersion = $nodePath ? trim(shell_exec("{$nodePath} -v 2>/dev/null") ?: '') : null;
            $npmVersion = $npmPath ? trim(shell_exec("{$npmPath} -v 2>/dev/null") ?: '') : null;
            $pm2Version = $pm2Path ? trim(shell_exec("sudo env PM2_HOME={$this->pm2Home} {$pm2Path} -v 2>/dev/null") ?: '') : null;

            return response()->json([
                'node' => [
                    'installed' => !empty($nodePath),
                    'path' => $nodePath,
                    'version' => $nodeVersion,
                ],
                'npm' => [
                    'installed' => !empty($npmPath),
                    'path' => $npmPath,
                    'version' => $npmVersion,
                ],
                'pm2' => [
                    'installed' => !empty($pm2Path) && !empty($pm2Version),
                    'path' => $pm2Path,
                    'version' => $pm2Version,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to check PM2 status: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Install PM2 globally via npm
     */
    public function installPm2()
    {
        if (\App\Support\LicenseGuard::isBlocked('critical')) {
            return response()->json(['error' => \App\Support\LicenseGuard::degradedMessage('pm2')], 503);
        }

        try {
            $user = auth()->user();
            if (!$user->isRootOrAdmin()) {
                return response()->json(['error' => 'Permission denied'], 403);
            }

            $output = [];
            $code = 0;
            exec("sudo npm install -g pm2 2>&1", $output, $code);

            if ($code !== 0) {
                return response()->json([
                    'error' => 'Failed to install PM2',
                    'details' => implode("\n", $output)
                ], 500);
            }

            // Setup systemd startup
            exec("sudo env PM2_HOME={$this->pm2Home} pm2 startup systemd -u root --hp /root 2>&1");
            exec("sudo env PM2_HOME={$this->pm2Home} pm2 save 2>&1");

            \App\Models\ActivityLog::log(
                'INSTALL_PM2',
                'PM2',
                'Installed PM2 globally and configured systemd startup'
            );

            return response()->json([
                'success' => true,
                'message' => 'PM2 installed and daemonized successfully'
            ]);
        } catch (\Exception $e) {
            Log::error("PM2 install error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get all PM2 processes with resource stats
     */
    public function getProcesses()
    {
        try {
            $output = [];
            $code = 0;
            exec("sudo env PM2_HOME={$this->pm2Home} pm2 jlist 2>&1", $output, $code);

            if ($code !== 0) {
                return response()->json([
                    'installed' => false,
                    'processes' => [],
                    'error' => 'PM2 is not running or not installed'
                ]);
            }

            $jsonStr = implode("\n", $output);
            // Locate JSON array in case pm2 outputted startup banners
            $start = strpos($jsonStr, '[');
            $end = strrpos($jsonStr, ']');
            if ($start !== false && $end !== false) {
                $jsonStr = substr($jsonStr, $start, $end - $start + 1);
            }

            $rawList = json_decode($jsonStr, true) ?: [];
            $processes = [];
            $totalMemory = 0;
            $totalCpu = 0;
            $onlineCount = 0;

            foreach ($rawList as $proc) {
                $pmId = $proc['pm_id'] ?? null;
                $name = $proc['name'] ?? 'unknown';
                $status = $proc['pm2_env']['status'] ?? 'stopped';
                $monit = $proc['monit'] ?? [];
                $cpu = (float)($monit['cpu'] ?? 0);
                $memoryBytes = (int)($monit['memory'] ?? 0);
                $memoryMb = round($memoryBytes / 1024 / 1024, 1);
                $pid = $proc['pid'] ?? 0;
                $restarts = $proc['pm2_env']['restart_time'] ?? 0;
                $uptimeMs = isset($proc['pm2_env']['pm_uptime']) ? (now()->getTimestamp() * 1000 - $proc['pm2_env']['pm_uptime']) : 0;
                $execMode = $proc['pm2_env']['exec_mode'] ?? 'fork_mode';
                $instances = $proc['pm2_env']['instances'] ?? 1;
                $cwd = $proc['pm2_env']['pm_cwd'] ?? '';
                $script = $proc['pm2_env']['pm_exec_path'] ?? '';
                $nodeVersion = $proc['pm2_env']['node_version'] ?? '';
                $unstableRestarts = $proc['pm2_env']['unstable_restarts'] ?? 0;

                if ($status === 'online') {
                    $onlineCount++;
                    $totalMemory += $memoryBytes;
                    $totalCpu += $cpu;
                }

                $processes[] = [
                    'id' => $pmId,
                    'name' => $name,
                    'status' => $status,
                    'pid' => $pid,
                    'cpu' => $cpu,
                    'memory_bytes' => $memoryBytes,
                    'memory_mb' => $memoryMb,
                    'restarts' => $restarts,
                    'uptime_human' => $this->formatUptime($uptimeMs),
                    'uptime_ms' => $uptimeMs,
                    'exec_mode' => str_contains($execMode, 'cluster') ? 'cluster' : 'fork',
                    'instances' => $instances,
                    'cwd' => $cwd,
                    'script' => basename($script),
                    'full_script' => $script,
                    'node_version' => $nodeVersion,
                    'unstable_restarts' => $unstableRestarts,
                ];
            }

            // Get available domains in /var/www for easy app linking
            $user = auth()->user();
            $domains = collect(File::directories($this->basePath))
                ->map(fn($p) => basename($p))
                ->filter(fn($n) => !in_array(strtolower($n), ['html', 'default', 'public', 'cgi-bin', 'nimbus']))
                ->values();

            return response()->json([
                'installed' => true,
                'processes' => $processes,
                'domains' => $domains,
                'stats' => [
                    'total' => count($processes),
                    'online' => $onlineCount,
                    'stopped' => count($processes) - $onlineCount,
                    'total_memory_mb' => round($totalMemory / 1024 / 1024, 1),
                    'total_cpu' => round($totalCpu, 1),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to get PM2 processes: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Start a PM2 process
     */
    public function startProcess(Request $request)
    {
        $request->validate(['id' => 'required']);
        $id = escapeshellarg($request->input('id'));

        try {
            $output = [];
            $code = 0;
            exec("sudo env PM2_HOME={$this->pm2Home} pm2 start {$id} 2>&1", $output, $code);
            exec("sudo env PM2_HOME={$this->pm2Home} pm2 save 2>&1");

            if ($code !== 0) {
                return response()->json(['error' => 'Failed to start process: ' . implode("\n", $output)], 400);
            }

            \App\Models\ActivityLog::log('PM2_START', 'PM2', "Started process {$request->input('id')}");
            return response()->json(['success' => true, 'message' => "Process started successfully"]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Stop a PM2 process
     */
    public function stopProcess(Request $request)
    {
        $request->validate(['id' => 'required']);
        $id = escapeshellarg($request->input('id'));

        try {
            $output = [];
            $code = 0;
            exec("sudo env PM2_HOME={$this->pm2Home} pm2 stop {$id} 2>&1", $output, $code);
            exec("sudo env PM2_HOME={$this->pm2Home} pm2 save 2>&1");

            if ($code !== 0) {
                return response()->json(['error' => 'Failed to stop process: ' . implode("\n", $output)], 400);
            }

            \App\Models\ActivityLog::log('PM2_STOP', 'PM2', "Stopped process {$request->input('id')}");
            return response()->json(['success' => true, 'message' => "Process stopped successfully"]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Restart a PM2 process
     */
    public function restartProcess(Request $request)
    {
        $request->validate(['id' => 'required']);
        $id = escapeshellarg($request->input('id'));

        try {
            $output = [];
            $code = 0;
            exec("sudo env PM2_HOME={$this->pm2Home} pm2 restart {$id} 2>&1", $output, $code);
            exec("sudo env PM2_HOME={$this->pm2Home} pm2 save 2>&1");

            if ($code !== 0) {
                return response()->json(['error' => 'Failed to restart process: ' . implode("\n", $output)], 400);
            }

            \App\Models\ActivityLog::log('PM2_RESTART', 'PM2', "Restarted process {$request->input('id')}");
            return response()->json(['success' => true, 'message' => "Process restarted successfully"]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a PM2 process
     */
    public function deleteProcess(Request $request)
    {
        $request->validate(['id' => 'required']);
        $id = escapeshellarg($request->input('id'));

        try {
            $output = [];
            $code = 0;
            exec("sudo env PM2_HOME={$this->pm2Home} pm2 delete {$id} 2>&1", $output, $code);
            exec("sudo env PM2_HOME={$this->pm2Home} pm2 save 2>&1");

            if ($code !== 0) {
                return response()->json(['error' => 'Failed to delete process: ' . implode("\n", $output)], 400);
            }

            \App\Models\ActivityLog::log('PM2_DELETE', 'PM2', "Deleted process {$request->input('id')}");
            return response()->json(['success' => true, 'message' => "Process deleted from PM2"]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Create and launch a new Node.js / PM2 process
     */
    public function createProcess(Request $request)
    {
        $request->validate([
            'name' => 'required|string|regex:/^[a-zA-Z0-9_\-\.]+$/|max:100',
            'script' => 'required|string|max:255',
            'cwd' => 'required|string|max:255',
            'instances' => 'nullable|string|max:10',
            'exec_mode' => 'nullable|string|in:fork,cluster',
            'port' => 'nullable|integer|between:1,65535',
            'args' => 'nullable|string|max:255',
            'max_memory_restart' => 'nullable|string|max:20',
            'env_vars' => 'nullable|array',
        ]);

        try {
            $name = $request->input('name');
            $script = $request->input('script');
            $cwd = $request->input('cwd');
            $instances = $request->input('instances', '1');
            $execMode = $request->input('exec_mode', 'fork');
            $port = $request->input('port');
            $args = $request->input('args', '');
            $maxMemory = $request->input('max_memory_restart', '500M');
            $envVars = $request->input('env_vars', []);

            if (!File::isDirectory($cwd)) {
                return response()->json(['error' => "Directory '{$cwd}' does not exist on the server."], 400);
            }

            // Build ENV string
            $envPrefix = "NODE_ENV=production ";
            if ($port) {
                $envPrefix .= "PORT={$port} ";
            }
            if (!empty($envVars)) {
                foreach ($envVars as $k => $v) {
                    if (!empty($k)) {
                        $envPrefix .= escapeshellarg($k) . "=" . escapeshellarg($v) . " ";
                    }
                }
            }

            // Build command
            $cmd = "cd " . escapeshellarg($cwd) . " && sudo env PM2_HOME={$this->pm2Home} {$envPrefix} pm2 start " . escapeshellarg($script) . " --name " . escapeshellarg($name);
            
            if ($instances === 'max' || (int)$instances > 1) {
                $cmd .= " -i " . escapeshellarg($instances);
            }
            if ($maxMemory) {
                $cmd .= " --max-memory-restart " . escapeshellarg($maxMemory);
            }
            if ($args) {
                $cmd .= " -- " . $args;
            }

            $output = [];
            $code = 0;
            exec("{$cmd} 2>&1", $output, $code);

            if ($code !== 0) {
                return response()->json([
                    'error' => 'Failed to launch PM2 process',
                    'details' => implode("\n", $output)
                ], 400);
            }

            exec("sudo env PM2_HOME={$this->pm2Home} pm2 save 2>&1");

            \App\Models\ActivityLog::log(
                'PM2_CREATE',
                'PM2',
                "Created and launched PM2 process '{$name}' (Script: {$script}, CWD: {$cwd})"
            );

            return response()->json([
                'success' => true,
                'message' => "Process '{$name}' launched and registered with PM2"
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Fetch logs for a specific PM2 process
     */
    public function getLogs(Request $request)
    {
        $request->validate(['id' => 'required']);
        $id = escapeshellarg($request->input('id'));
        $lines = min((int)$request->input('lines', 100), 500);

        try {
            $output = [];
            $code = 0;
            exec("sudo env PM2_HOME={$this->pm2Home} pm2 logs {$id} --lines {$lines} --nostream 2>&1", $output, $code);

            return response()->json([
                'success' => true,
                'logs' => implode("\n", $output)
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Reload all PM2 processes (zero downtime)
     */
    public function reloadAll()
    {
        try {
            $output = [];
            $code = 0;
            exec("sudo env PM2_HOME={$this->pm2Home} pm2 reload all 2>&1", $output, $code);
            exec("sudo env PM2_HOME={$this->pm2Home} pm2 save 2>&1");

            \App\Models\ActivityLog::log('PM2_RELOAD_ALL', 'PM2', 'Reloaded all PM2 processes');

            return response()->json([
                'success' => true,
                'message' => 'All PM2 processes reloaded with zero downtime'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Format milliseconds into human readable uptime string
     */
    private function formatUptime(int $ms): string
    {
        if ($ms <= 0) return '0s';
        $seconds = (int)($ms / 1000);
        $days = (int)($seconds / 86400);
        $hours = (int)(($seconds % 86400) / 3600);
        $minutes = (int)(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        $parts = [];
        if ($days > 0) $parts[] = "{$days}d";
        if ($hours > 0) $parts[] = "{$hours}h";
        if ($minutes > 0) $parts[] = "{$minutes}m";
        if (empty($parts) || count($parts) < 2) $parts[] = "{$secs}s";

        return implode(' ', array_slice($parts, 0, 2));
    }
}
