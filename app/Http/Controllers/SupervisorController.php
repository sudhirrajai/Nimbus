<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SupervisorController extends Controller
{
    private function getDefaultProcessUser(): string
    {
        return env('NIMBUS_GIT_USER', 'www-data');
    }

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
     * Get list of projects in /var/www
     */
    public function getProjects()
    {
        try {
            $projects = [];
            $wwwPath = '/var/www';
            
            if (is_dir($wwwPath)) {
                $dirs = scandir($wwwPath);
                foreach ($dirs as $dir) {
                    if ($dir === '.' || $dir === '..') continue;
                    $fullPath = "{$wwwPath}/{$dir}";
                    if (is_dir($fullPath)) {
                        // Check if it's a Laravel project (has artisan file)
                        $isLaravel = file_exists("{$fullPath}/artisan");
                        $projects[] = [
                            'name' => $dir,
                            'path' => $fullPath,
                            'isLaravel' => $isLaravel
                        ];
                    }
                }
            }
            
            return response()->json([
                'success' => true,
                'projects' => $projects
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
     * Get all supervisor processes grouped by their config
     */
    public function getProcesses()
    {
        try {
            $groups = [];
            
            // Get process status from supervisorctl
            exec('sudo supervisorctl status 2>/dev/null', $output, $code);
            
            foreach ($output as $line) {
                if (empty(trim($line))) continue;
                
                // Parse line like: "myapp:myapp_00 RUNNING pid 12345, uptime 0:10:00"
                if (preg_match('/^(\S+)\s+(RUNNING|STOPPED|STARTING|BACKOFF|STOPPING|EXITED|FATAL|UNKNOWN)\s*(.*)$/', $line, $matches)) {
                    $fullName = $matches[1];
                    $status = $matches[2];
                    $info = $matches[3];
                    
                    // Split group and process name
                    if (str_contains($fullName, ':')) {
                        [$groupName, $processName] = explode(':', $fullName, 2);
                    } else {
                        $groupName = $fullName;
                        $processName = $fullName;
                    }

                    $pid = null;
                    $uptime = null;
                    
                    if (preg_match('/pid\s+(\d+)/', $info, $pidMatch)) {
                        $pid = $pidMatch[1];
                    }
                    if (preg_match('/uptime\s+([\d:]+)/', $info, $uptimeMatch)) {
                        $uptime = $uptimeMatch[1];
                    }
                    
                    if (!isset($groups[$groupName])) {
                        $groups[$groupName] = [
                            'name' => $groupName,
                            'processes' => [],
                            'status' => 'STOPPED', // Will be updated
                            'count' => 0
                        ];
                    }

                    $groups[$groupName]['processes'][] = [
                        'name' => $processName,
                        'fullName' => $fullName,
                        'status' => $status,
                        'pid' => $pid,
                        'uptime' => $uptime,
                        'info' => $info
                    ];
                    $groups[$groupName]['count']++;

                    // If any process in group is running, group is considered "active"
                    if ($status === 'RUNNING') {
                        $groups[$groupName]['status'] = 'RUNNING';
                    }
                }
            }

            // Also get config files to ensure we show configs that might not have running processes
            $configDir = '/etc/supervisor/conf.d';
            if (is_dir($configDir)) {
                $files = glob("{$configDir}/*.conf");
                foreach ($files as $file) {
                    $configName = basename($file, '.conf');
                    if (!isset($groups[$configName])) {
                        $groups[$configName] = [
                            'name' => $configName,
                            'processes' => [],
                            'status' => 'STOPPED',
                            'count' => 0
                        ];
                    }
                }
            }

            return response()->json([
                'success' => true,
                'groups' => array_values($groups),
                'count' => count($groups)
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
                'project' => 'nullable|string',
                'command' => 'required|string',
                'directory' => 'nullable|string',
                'environment' => 'nullable|string',
                'user' => 'nullable|string',
                'numprocs' => 'nullable|integer|min:1|max:10',
                'autostart' => 'nullable|boolean',
                'autorestart' => 'nullable|boolean',
                'logfile' => 'nullable|string'
            ]);

            $name = $request->input('name');
            $project = $request->input('project');
            $command = $request->input('command');
            $directory = $request->input('directory') ?: ($project ? "/var/www/{$project}" : "");
            $environment = $request->input('environment');
            $numprocs = $request->input('numprocs', 1);
            $autostart = $request->input('autostart', true) ? 'true' : 'false';
            $autorestart = $request->input('autorestart', true) ? 'true' : 'false';
            $logfile = $request->input('logfile', 'worker.log');
            $processUser = $request->input('user', $this->getDefaultProcessUser());
            
            $stdout = $project ? "/var/www/{$project}/{$logfile}" : "/var/log/supervisor/{$name}.log";

            $config = <<<CONFIG
[program:{$name}]
process_name=%(program_name)s_%(process_num)02d
command={$command}
CONFIG;

            if ($directory) {
                $config .= "\ndirectory={$directory}";
            }
            
            $config .= <<<CONFIG

autostart={$autostart}
autorestart={$autorestart}
user={$processUser}
numprocs={$numprocs}
redirect_stderr=true
stdout_logfile={$stdout}
stopwaitsecs=3600
CONFIG;

            if ($environment) {
                $config .= "\nenvironment={$environment}";
            }

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

    /**
     * Get process configuration
     */
    public function getProcessConfig(Request $request)
    {
        try {
            $name = $request->input('name');
            
            // If name is like "group:process", we want the "group" part for the config file
            $configName = str_contains($name, ':') ? explode(':', $name)[0] : $name;
            $configPath = "/etc/supervisor/conf.d/{$configName}.conf";
            
            if (!file_exists($configPath)) {
                return response()->json(['error' => "Configuration not found at {$configPath}"], 404);
            }
            
            $content = file_get_contents($configPath);
            
            // Parse config file
            $config = [
                'name' => $name,
                'project' => '',
                'command' => '',
                'directory' => '',
                'environment' => '',
                'user' => 'www-data',
                'numprocs' => 1,
                'autostart' => true,
                'autorestart' => true,
                'sleep' => 3,
                'tries' => 3,
                'timeout' => 120,
                'logfile' => 'worker.log'
            ];
            
            // Parse command
            if (preg_match('/command\s*=\s*(.+)$/m', $content, $m)) {
                $config['command'] = trim($m[1]);
                $command = $config['command'];
                
                // Try to extract Laravel options if it looks like one
                if (str_contains($command, 'artisan queue:work')) {
                    if (preg_match('#/var/www/([^/]+)/#', $command, $pm)) {
                        $config['project'] = $pm[1];
                    }
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
            }
            
            if (preg_match('/directory\s*=\s*(.+)$/m', $content, $m)) {
                $config['directory'] = trim($m[1]);
            }
            if (preg_match('/environment\s*=\s*(.+)$/m', $content, $m)) {
                $config['environment'] = trim($m[1]);
            }
            if (preg_match('/numprocs\s*=\s*(\d+)/m', $content, $m)) {
                $config['numprocs'] = (int)$m[1];
            }
            if (preg_match('/user\s*=\s*(.+)$/m', $content, $m)) {
                $config['user'] = trim($m[1]);
            }
            if (preg_match('/autostart\s*=\s*(true|false)/m', $content, $m)) {
                $config['autostart'] = $m[1] === 'true';
            }
            if (preg_match('/autorestart\s*=\s*(true|false)/m', $content, $m)) {
                $config['autorestart'] = $m[1] === 'true';
            }
            if (preg_match('/stdout_logfile\s*=\s*(.+)$/m', $content, $m)) {
                $logPath = trim($m[1]);
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
            $command = $request->input('command');
            $directory = $request->input('directory') ?: ($project ? "/var/www/{$project}" : "");
            $environment = $request->input('environment');
            $processUser = $request->input('user', $this->getDefaultProcessUser());
            $numprocs = $request->input('numprocs', 1);
            $autostart = $request->input('autostart', true) ? 'true' : 'false';
            $autorestart = $request->input('autorestart', true) ? 'true' : 'false';
            $logfile = $request->input('logfile', 'worker.log');
            
            $stdout = $project ? "/var/www/{$project}/{$logfile}" : "/var/log/supervisor/{$name}.log";

            $config = <<<CONFIG
[program:{$name}]
process_name=%(program_name)s_%(process_num)02d
command={$command}
CONFIG;

            if ($directory) {
                $config .= "\ndirectory={$directory}";
            }
            
            $config .= <<<CONFIG

autostart={$autostart}
autorestart={$autorestart}
user={$processUser}
numprocs={$numprocs}
redirect_stderr=true
stdout_logfile={$stdout}
stopwaitsecs=3600
CONFIG;

            if ($environment) {
                $config .= "\nenvironment={$environment}";
            }

            $configPath = "/etc/supervisor/conf.d/{$name}.conf";
            $tempFile = "/tmp/{$name}.conf";
            file_put_contents($tempFile, $config);
            exec("sudo mv {$tempFile} {$configPath}");
            exec("sudo supervisorctl reread");
            exec("sudo supervisorctl update");
            exec("sudo supervisorctl restart {$name}:* 2>/dev/null");

            return response()->json([
                'success' => true,
                'message' => "Process {$name} updated successfully"
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
