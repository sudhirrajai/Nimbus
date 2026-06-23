<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class LogsController extends Controller
{
    /**
     * Display logs management page
     */
    public function index()
    {
        return Inertia::render('Logs/Index');
    }

    /**
     * Get available log files
     */
    public function getLogFiles()
    {
        try {
            $logs = [];
            $user = auth()->user();
            
            if ($user->isRoot()) {
                // Nginx logs
                $nginxLogs = [
                    ['name' => 'Nginx Access Log', 'path' => '/var/log/nginx/access.log', 'type' => 'nginx'],
                    ['name' => 'Nginx Error Log', 'path' => '/var/log/nginx/error.log', 'type' => 'nginx'],
                ];
                
                // System logs
                $systemLogs = [
                    ['name' => 'Syslog', 'path' => '/var/log/syslog', 'type' => 'system'],
                    ['name' => 'Auth Log', 'path' => '/var/log/auth.log', 'type' => 'system'],
                    ['name' => 'Kern Log', 'path' => '/var/log/kern.log', 'type' => 'system'],
                ];
                
                // PHP logs
                $phpLogs = [
                    ['name' => 'PHP Error Log', 'path' => '/var/log/php8.1-fpm.log', 'type' => 'php'],
                    ['name' => 'PHP-FPM Log', 'path' => '/var/log/php-fpm/error.log', 'type' => 'php'],
                ];
                
                // Supervisor logs
                $supervisorLogs = [
                    ['name' => 'Supervisor Log', 'path' => '/var/log/supervisor/supervisord.log', 'type' => 'supervisor'],
                ];
                
                // Check which logs exist and are readable
                foreach (array_merge($nginxLogs, $systemLogs, $phpLogs, $supervisorLogs) as $log) {
                    if (file_exists($log['path'])) {
                        $log['size'] = $this->formatBytes(filesize($log['path']));
                        $log['modified'] = date('Y-m-d H:i:s', filemtime($log['path']));
                        $logs[] = $log;
                    }
                }
            }
            
            // Add domain-specific logs
            $accessibleDomains = $user->accessibleDomains();
            foreach ($accessibleDomains as $domain) {
                // Laravel log
                $laravelLog = "/var/www/{$domain}/storage/logs/laravel.log";
                if (file_exists($laravelLog)) {
                    $logs[] = [
                        'name' => "{$domain} - Laravel Log",
                        'path' => $laravelLog,
                        'type' => 'laravel',
                        'size' => $this->formatBytes(filesize($laravelLog)),
                        'modified' => date('Y-m-d H:i:s', filemtime($laravelLog))
                    ];
                }

                // Nginx access log
                $nginxAccess = "/var/log/nginx/{$domain}.access.log";
                if (file_exists($nginxAccess)) {
                    $logs[] = [
                        'name' => "{$domain} - Nginx Access Log",
                        'path' => $nginxAccess,
                        'type' => 'nginx',
                        'size' => $this->formatBytes(filesize($nginxAccess)),
                        'modified' => date('Y-m-d H:i:s', filemtime($nginxAccess))
                    ];
                }

                // Nginx error log
                $nginxError = "/var/log/nginx/{$domain}.error.log";
                if (file_exists($nginxError)) {
                    $logs[] = [
                        'name' => "{$domain} - Nginx Error Log",
                        'path' => $nginxError,
                        'type' => 'nginx',
                        'size' => $this->formatBytes(filesize($nginxError)),
                        'modified' => date('Y-m-d H:i:s', filemtime($nginxError))
                    ];
                }
            }
            
            return response()->json([
                'success' => true,
                'logs' => $logs
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Read log file content
     */
    public function readLog(Request $request)
    {
        try {
            $path = $request->input('path');
            $lines = $request->input('lines', 100);
            
            if (!$this->canAccessLogPath($path)) {
                return response()->json(['error' => 'Permission denied'], 403);
            }

            if (!file_exists($path)) {
                return response()->json(['error' => 'Log file not found'], 404);
            }
            
            // Use tail to get last N lines
            $output = [];
            exec("sudo tail -n {$lines} " . escapeshellarg($path) . " 2>&1", $output);
            
            return response()->json([
                'success' => true,
                'content' => implode("\n", $output),
                'path' => $path
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Clear/truncate a log file
     */
    public function clearLog(Request $request)
    {
        try {
            $path = $request->input('path');
            
            if (!$this->canAccessLogPath($path)) {
                return response()->json(['error' => 'Permission denied'], 403);
            }

            if (!file_exists($path)) {
                return response()->json(['error' => 'Log file not found'], 404);
            }
            
            exec("sudo truncate -s 0 " . escapeshellarg($path) . " 2>&1", $output, $code);
            
            if ($code !== 0) {
                return response()->json(['error' => 'Failed to clear log'], 500);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Log cleared successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Download log file
     */
    public function downloadLog(Request $request)
    {
        $path = $request->input('path');
        
        if (!$this->canAccessLogPath($path)) {
            abort(403, 'Permission denied');
        }

        if (!file_exists($path)) {
            abort(404, 'Log file not found');
        }
        
        return response()->download($path);
    }

    /**
     * Check if a log file is accessible by the current user
     */
    private function canAccessLogPath(string $path): bool
    {
        $user = auth()->user();
        if ($user->isRoot()) {
            return true;
        }

        $accessibleDomains = $user->accessibleDomains();
        foreach ($accessibleDomains as $domain) {
            $laravelLog = "/var/www/{$domain}/storage/logs/laravel.log";
            $nginxAccess = "/var/log/nginx/{$domain}.access.log";
            $nginxError = "/var/log/nginx/{$domain}.error.log";

            $realPath = realpath($path);
            if ($realPath !== false) {
                $realLaravel = realpath($laravelLog);
                $realNginxAccess = realpath($nginxAccess);
                $realNginxError = realpath($nginxError);

                if (($realLaravel !== false && $realPath === $realLaravel) || 
                    ($realNginxAccess !== false && $realPath === $realNginxAccess) || 
                    ($realNginxError !== false && $realPath === $realNginxError)) {
                    return true;
                }
            }
            
            if (str_replace('\\', '/', $path) === $laravelLog ||
                str_replace('\\', '/', $path) === $nginxAccess ||
                str_replace('\\', '/', $path) === $nginxError) {
                return true;
            }
        }

        return false;
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
