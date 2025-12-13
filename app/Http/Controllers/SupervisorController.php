<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SupervisorController extends Controller
{
    /**
     * Display supervisor management page
     */
    public function index()
    {
        return Inertia::render('Supervisor/Index');
    }

    /**
     * Get supervisor status
     */
    public function getStatus()
    {
        $installed = file_exists('/usr/bin/supervisord') || file_exists('/usr/local/bin/supervisord');
        $running = false;
        
        if ($installed) {
            exec('pgrep -f supervisord 2>/dev/null', $output, $code);
            $running = $code === 0;
        }

        return response()->json([
            'installed' => $installed,
            'running' => $running
        ]);
    }

    /**
     * Get list of system users suitable for running processes
     */
    public function getSystemUsers()
    {
        try {
            $users = [];
            
            // Read /etc/passwd and filter regular users
            $passwd = file_get_contents('/etc/passwd');
            $lines = explode("\n", $passwd);
            
            foreach ($lines as $line) {
                if (empty($line)) continue;
                
                $parts = explode(':', $line);
                if (count($parts) < 7) continue;
                
                $username = $parts[0];
                $uid = (int)$parts[2];
                $shell = $parts[6];
                
                // Include:
                // - Regular users (UID >= 1000)
                // - www-data (commonly used for web apps)
                // - root (for system processes)
                // - users with valid shells
                $validShells = ['/bin/bash', '/bin/sh', '/bin/zsh', '/usr/bin/bash', '/usr/bin/zsh'];
                
                if ($uid >= 1000 || $username === 'www-data' || $username === 'root') {
                    if ($username === 'nobody' || str_contains($shell, 'nologin') || str_contains($shell, 'false')) {
                        continue;
                    }
                    
                    $users[] = [
                        'username' => $username,
                        'uid' => $uid
                    ];
                }
            }
            
            // Sort by username
            usort($users, fn($a, $b) => strcmp($a['username'], $b['username']));
            
            return response()->json([
                'success' => true,
                'users' => $users
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Install supervisor
     */
    public function install()
    {
        try {
            $logFile = storage_path('logs/supervisor_install.log');
            $statusFile = storage_path('logs/supervisor_install.status');
            
            file_put_contents($logFile, "=== Supervisor Installation Started ===\n");
            file_put_contents($logFile, "Time: " . date('Y-m-d H:i:s') . "\n\n", FILE_APPEND);
            file_put_contents($statusFile, 'running');

            $script = <<<'BASH'
#!/bin/bash
export DEBIAN_FRONTEND=noninteractive

echo "[1/3] Installing Supervisor..."
sudo apt-get update
sudo apt-get install -y supervisor

echo ""
echo "[2/3] Starting Supervisor service..."
sudo systemctl enable supervisor
sudo systemctl start supervisor

echo ""
echo "[3/3] Verifying installation..."
supervisorctl status

echo ""
echo "=========================================="
echo "  Supervisor Installation Complete!"
echo "=========================================="
BASH;

            $scriptPath = '/tmp/install_supervisor.sh';
            file_put_contents($scriptPath, $script);
            chmod($scriptPath, 0755);

            $command = "sudo bash {$scriptPath} >> {$logFile} 2>&1; echo \$? > {$statusFile}";
            exec("nohup bash -c '{$command}' > /dev/null 2>&1 &");

            return response()->json([
                'success' => true,
                'message' => 'Installation started'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get installation log
     */
    public function getInstallLog()
    {
        $logFile = storage_path('logs/supervisor_install.log');
        $statusFile = storage_path('logs/supervisor_install.status');
        
        $log = file_exists($logFile) ? file_get_contents($logFile) : '';
        $status = file_exists($statusFile) ? trim(file_get_contents($statusFile)) : 'unknown';
        
        return response()->json([
            'log' => $log,
            'status' => $status,
            'isRunning' => $status === 'running',
            'isComplete' => $status === '0',
            'isFailed' => $status !== 'running' && $status !== '0' && $status !== 'unknown'
        ]);
    }

    /**
     * Get all supervisor processes
     */
    public function getProcesses()
    {
        try {
            $processes = [];
            
            // Get process status from supervisorctl
            exec('sudo supervisorctl status 2>/dev/null', $output, $code);
            
            foreach ($output as $line) {
                if (empty(trim($line))) continue;
                
                // Parse line like: "myapp:myapp_00 RUNNING pid 12345, uptime 0:10:00"
                if (preg_match('/^(\S+)\s+(RUNNING|STOPPED|STARTING|BACKOFF|STOPPING|EXITED|FATAL|UNKNOWN)\s*(.*)$/', $line, $matches)) {
                    $name = $matches[1];
                    $status = $matches[2];
                    $info = $matches[3];
                    
                    $pid = null;
                    $uptime = null;
                    
                    if (preg_match('/pid\s+(\d+)/', $info, $pidMatch)) {
                        $pid = $pidMatch[1];
                    }
                    if (preg_match('/uptime\s+([\d:]+)/', $info, $uptimeMatch)) {
                        $uptime = $uptimeMatch[1];
                    }
                    
                    $processes[] = [
                        'name' => $name,
                        'status' => $status,
                        'pid' => $pid,
                        'uptime' => $uptime,
                        'info' => $info
                    ];
                }
            }

            // Also get config files
            $configDir = '/etc/supervisor/conf.d';
            $configs = [];
            if (is_dir($configDir)) {
                $files = glob("{$configDir}/*.conf");
                foreach ($files as $file) {
                    $configs[] = basename($file, '.conf');
                }
            }

            return response()->json([
                'success' => true,
                'processes' => $processes,
                'configs' => $configs
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Start a process
     */
    public function startProcess(Request $request)
    {
        try {
            $name = $request->input('name');
            exec("sudo supervisorctl start {$name} 2>&1", $output, $code);
            
            return response()->json([
                'success' => $code === 0,
                'message' => implode("\n", $output)
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Stop a process
     */
    public function stopProcess(Request $request)
    {
        try {
            $name = $request->input('name');
            exec("sudo supervisorctl stop {$name} 2>&1", $output, $code);
            
            return response()->json([
                'success' => $code === 0,
                'message' => implode("\n", $output)
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Restart a process
     */
    public function restartProcess(Request $request)
    {
        try {
            $name = $request->input('name');
            exec("sudo supervisorctl restart {$name} 2>&1", $output, $code);
            
            return response()->json([
                'success' => $code === 0,
                'message' => implode("\n", $output)
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Create new supervisor process
     */
    public function createProcess(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|alpha_dash',
                'command' => 'required|string',
                'directory' => 'nullable|string',
                'user' => 'nullable|string',
                'numprocs' => 'nullable|integer|min:1|max:100',
                'autostart' => 'nullable|boolean',
                'autorestart' => 'nullable|boolean',
                'stdout_logfile' => 'nullable|string',
                'stderr_logfile' => 'nullable|string'
            ]);

            $name = $request->input('name');
            $command = $request->input('command');
            $directory = $request->input('directory', '/tmp');
            $user = $request->input('user', 'www-data');
            $numprocs = $request->input('numprocs', 1);
            $autostart = $request->input('autostart', true) ? 'true' : 'false';
            $autorestart = $request->input('autorestart', true) ? 'true' : 'false';
            $stdoutLog = $request->input('stdout_logfile', "/var/log/supervisor/{$name}.log");
            $stderrLog = $request->input('stderr_logfile', "/var/log/supervisor/{$name}.error.log");

            $config = <<<CONFIG
[program:{$name}]
command={$command}
directory={$directory}
user={$user}
numprocs={$numprocs}
autostart={$autostart}
autorestart={$autorestart}
stdout_logfile={$stdoutLog}
stderr_logfile={$stderrLog}
stdout_logfile_maxbytes=10MB
stderr_logfile_maxbytes=10MB
CONFIG;

            $configPath = "/etc/supervisor/conf.d/{$name}.conf";
            $tempFile = "/tmp/{$name}.conf";
            file_put_contents($tempFile, $config);
            exec("sudo mv {$tempFile} {$configPath}");
            exec("sudo supervisorctl reread");
            exec("sudo supervisorctl update");

            return response()->json([
                'success' => true,
                'message' => "Process {$name} created successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a process
     */
    public function deleteProcess(Request $request)
    {
        try {
            $name = $request->input('name');
            
            // Stop the process first
            exec("sudo supervisorctl stop {$name} 2>/dev/null");
            
            // Remove config file
            $configPath = "/etc/supervisor/conf.d/{$name}.conf";
            exec("sudo rm -f {$configPath}");
            
            // Update supervisor
            exec("sudo supervisorctl reread");
            exec("sudo supervisorctl update");

            return response()->json([
                'success' => true,
                'message' => "Process {$name} deleted successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * View process logs
     */
    public function viewLogs(Request $request)
    {
        try {
            $name = $request->input('name');
            $type = $request->input('type', 'stdout'); // stdout or stderr
            $lines = $request->input('lines', 100);
            
            $logFile = "/var/log/supervisor/{$name}." . ($type === 'stderr' ? 'error.' : '') . "log";
            
            if (!file_exists($logFile)) {
                // Try alternative path
                exec("sudo supervisorctl tail -100 {$name} {$type} 2>&1", $output, $code);
                return response()->json([
                    'success' => true,
                    'log' => implode("\n", $output)
                ]);
            }
            
            exec("sudo tail -n {$lines} {$logFile} 2>&1", $output, $code);
            
            return response()->json([
                'success' => true,
                'log' => implode("\n", $output)
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Reload supervisor configuration
     */
    public function reloadConfig()
    {
        try {
            exec("sudo supervisorctl reread 2>&1", $output1);
            exec("sudo supervisorctl update 2>&1", $output2);
            
            return response()->json([
                'success' => true,
                'message' => implode("\n", array_merge($output1, $output2))
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
