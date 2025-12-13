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
                'project' => 'required|string',
                'numprocs' => 'nullable|integer|min:1|max:10',
                'autostart' => 'nullable|boolean',
                'autorestart' => 'nullable|boolean',
                'sleep' => 'nullable|integer|min:1|max:60',
                'tries' => 'nullable|integer|min:1|max:10',
                'timeout' => 'nullable|integer|min:30|max:3600',
                'logfile' => 'nullable|string'
            ]);

            $name = $request->input('name');
            $project = $request->input('project');
            $numprocs = $request->input('numprocs', 1);
            $autostart = $request->input('autostart', true) ? 'true' : 'false';
            $autorestart = $request->input('autorestart', true) ? 'true' : 'false';
            $sleep = $request->input('sleep', 3);
            $tries = $request->input('tries', 3);
            $timeout = $request->input('timeout', 120);
            $logfile = $request->input('logfile', 'worker.log');
            
            $directory = "/var/www/{$project}";
            $command = "/usr/bin/php {$directory}/artisan queue:work --sleep={$sleep} --tries={$tries} --timeout={$timeout}";
            $stdout = "{$directory}/{$logfile}";

            $config = <<<CONFIG
[program:{$name}]
process_name=%(program_name)s_%(process_num)02d
command={$command}
autostart={$autostart}
autorestart={$autorestart}
user=www-data
numprocs={$numprocs}
redirect_stderr=true
stdout_logfile={$stdout}
stopwaitsecs=3600
CONFIG;

            $configPath = "/etc/supervisor/conf.d/{$name}.conf";
            $tempFile = "/tmp/{$name}.conf";
            file_put_contents($tempFile, $config);
            exec("sudo mv {$tempFile} {$configPath}");
            exec("sudo supervisorctl reread");
            exec("sudo supervisorctl update");

            return response()->json([
                'success' => true,
                'message' => "Worker {$name} created successfully"
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

    /**
     * Get process configuration
     */
    public function getProcessConfig(Request $request)
    {
        try {
            $name = $request->input('name');
            $configPath = "/etc/supervisor/conf.d/{$name}.conf";
            
            if (!file_exists($configPath)) {
                return response()->json(['error' => 'Configuration not found'], 404);
            }
            
            $content = file_get_contents($configPath);
            
            // Parse config file
            $config = [
                'name' => $name,
                'project' => '',
                'numprocs' => 1,
                'autostart' => true,
                'autorestart' => true,
                'sleep' => 3,
                'tries' => 3,
                'timeout' => 120,
                'logfile' => 'worker.log'
            ];
            
            // Parse command to extract project and options
            if (preg_match('/command\s*=\s*(.+)$/m', $content, $m)) {
                $command = trim($m[1]);
                // Extract project from path like /var/www/project/artisan
                if (preg_match('#/var/www/([^/]+)/#', $command, $pm)) {
                    $config['project'] = $pm[1];
                }
                // Extract --sleep, --tries, --timeout
                if (preg_match('/--sleep=(\d+)/', $command, $sm)) {
                    $config['sleep'] = (int)$sm[1];
                }
                if (preg_match('/--tries=(\d+)/', $command, $tm)) {
                    $config['tries'] = (int)$tm[1];
                }
                if (preg_match('/--timeout=(\d+)/', $command, $tom)) {
                    $config['timeout'] = (int)$tom[1];
                }
            }
            
            if (preg_match('/numprocs\s*=\s*(\d+)/m', $content, $m)) {
                $config['numprocs'] = (int)$m[1];
            }
            if (preg_match('/autostart\s*=\s*(true|false)/m', $content, $m)) {
                $config['autostart'] = $m[1] === 'true';
            }
            if (preg_match('/autorestart\s*=\s*(true|false)/m', $content, $m)) {
                $config['autorestart'] = $m[1] === 'true';
            }
            if (preg_match('/stdout_logfile\s*=\s*(.+)$/m', $content, $m)) {
                $logPath = trim($m[1]);
                // Extract just the filename from path
                $config['logfile'] = basename($logPath);
            }
            
            return response()->json([
                'success' => true,
                'config' => $config,
                'raw' => $content
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update process configuration
     */
    public function updateProcess(Request $request)
    {
        try {
            $name = $request->input('name');
            $project = $request->input('project');
            $numprocs = $request->input('numprocs', 1);
            $autostart = $request->input('autostart', true) ? 'true' : 'false';
            $autorestart = $request->input('autorestart', true) ? 'true' : 'false';
            $sleep = $request->input('sleep', 3);
            $tries = $request->input('tries', 3);
            $timeout = $request->input('timeout', 120);
            $logfile = $request->input('logfile', 'worker.log');
            
            $directory = "/var/www/{$project}";
            $command = "/usr/bin/php {$directory}/artisan queue:work --sleep={$sleep} --tries={$tries} --timeout={$timeout}";
            $stdout = "{$directory}/{$logfile}";

            $config = <<<CONFIG
[program:{$name}]
process_name=%(program_name)s_%(process_num)02d
command={$command}
autostart={$autostart}
autorestart={$autorestart}
user=www-data
numprocs={$numprocs}
redirect_stderr=true
stdout_logfile={$stdout}
stopwaitsecs=3600
CONFIG;

            $configPath = "/etc/supervisor/conf.d/{$name}.conf";
            $tempFile = "/tmp/{$name}.conf";
            file_put_contents($tempFile, $config);
            exec("sudo mv {$tempFile} {$configPath}");
            exec("sudo supervisorctl reread");
            exec("sudo supervisorctl update");
            exec("sudo supervisorctl restart {$name}:* 2>/dev/null");

            return response()->json([
                'success' => true,
                'message' => "Worker {$name} updated successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Start all processes
     */
    public function startAll()
    {
        try {
            exec("sudo supervisorctl start all 2>&1", $output, $code);
            return response()->json([
                'success' => $code === 0,
                'message' => implode("\n", $output)
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Stop all processes
     */
    public function stopAll()
    {
        try {
            exec("sudo supervisorctl stop all 2>&1", $output, $code);
            return response()->json([
                'success' => $code === 0,
                'message' => implode("\n", $output)
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Restart all processes
     */
    public function restartAll()
    {
        try {
            exec("sudo supervisorctl restart all 2>&1", $output, $code);
            return response()->json([
                'success' => $code === 0,
                'message' => implode("\n", $output)
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
