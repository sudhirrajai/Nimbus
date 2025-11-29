<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $serverStats = $this->getServerStats();
        
        return Inertia::render('Home', [
            'serverStats' => $serverStats
        ]);
    }

    public function getStats()
    {
        return response()->json($this->getServerStats());
    }

    private function getServerStats()
    {
        return [
            'cpu' => $this->getCpuUsage(),
            'memory' => $this->getMemoryUsage(),
            'disk' => $this->getDiskUsage(),
            'load' => $this->getLoadAverage(),
            'uptime' => $this->getUptime(),
            'processes' => $this->getProcessCount(),
        ];
    }

    private function getCpuUsage()
    {
        $load = sys_getloadavg();
        $cores = $this->getCpuCores();
        
        // Calculate CPU usage percentage based on load average
        $cpuUsage = ($load[0] / $cores) * 100;
        $cpuUsage = min($cpuUsage, 100); // Cap at 100%
        
        return [
            'usage' => round($cpuUsage, 2),
            'cores' => $cores,
            'load_1min' => round($load[0], 2),
            'load_5min' => round($load[1], 2),
            'load_15min' => round($load[2], 2),
        ];
    }

    private function getCpuCores()
    {
        $cores = 1;
        
        if (PHP_OS_FAMILY === 'Linux') {
            $output = @shell_exec('nproc');
            if ($output !== null) {
                $cores = (int) trim($output);
            }
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $output = @shell_exec('sysctl -n hw.ncpu');
            if ($output !== null) {
                $cores = (int) trim($output);
            }
        } elseif (PHP_OS_FAMILY === 'Windows') {
            $cores = (int) getenv('NUMBER_OF_PROCESSORS');
        }
        
        return $cores > 0 ? $cores : 1;
    }

    private function getMemoryUsage()
    {
        $free = 0;
        $total = 0;
        $used = 0;
        
        if (PHP_OS_FAMILY === 'Linux') {
            $meminfo = @file_get_contents('/proc/meminfo');
            
            if ($meminfo) {
                preg_match('/MemTotal:\s+(\d+)/', $meminfo, $matchTotal);
                preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $matchAvailable);
                
                if (isset($matchTotal[1]) && isset($matchAvailable[1])) {
                    $total = $matchTotal[1] * 1024; // Convert KB to bytes
                    $available = $matchAvailable[1] * 1024;
                    $used = $total - $available;
                    $free = $available;
                }
            }
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $output = @shell_exec('vm_stat | grep "Pages free" | awk \'{print $3}\' | tr -d \'.\'');
            $pageSize = 4096;
            if ($output !== null) {
                $free = (int) trim($output) * $pageSize;
            }
            
            $totalOutput = @shell_exec('sysctl -n hw.memsize');
            if ($totalOutput !== null) {
                $total = (int) trim($totalOutput);
                $used = $total - $free;
            }
        }
        
        // Fallback if we couldn't get memory info
        if ($total === 0) {
            $total = 8 * 1024 * 1024 * 1024; // Default 8GB
            $used = $total * 0.5; // Assume 50% used
            $free = $total - $used;
        }
        
        $usagePercent = $total > 0 ? ($used / $total) * 100 : 0;
        
        return [
            'total' => $this->formatBytes($total),
            'used' => $this->formatBytes($used),
            'free' => $this->formatBytes($free),
            'usage_percent' => round($usagePercent, 2),
            'total_bytes' => $total,
            'used_bytes' => $used,
        ];
    }

    private function getDiskUsage()
    {
        $rootPath = DIRECTORY_SEPARATOR;
        
        $total = @disk_total_space($rootPath);
        $free = @disk_free_space($rootPath);
        
        if ($total === false || $free === false) {
            $total = 100 * 1024 * 1024 * 1024; // Default 100GB
            $free = $total * 0.5;
        }
        
        $used = $total - $free;
        $usagePercent = $total > 0 ? ($used / $total) * 100 : 0;
        
        return [
            'total' => $this->formatBytes($total),
            'used' => $this->formatBytes($used),
            'free' => $this->formatBytes($free),
            'usage_percent' => round($usagePercent, 2),
            'total_bytes' => $total,
            'used_bytes' => $used,
        ];
    }

    private function getLoadAverage()
    {
        $load = sys_getloadavg();
        
        return [
            '1min' => round($load[0], 2),
            '5min' => round($load[1], 2),
            '15min' => round($load[2], 2),
        ];
    }

    private function getUptime()
    {
        $uptime = 0;
        
        if (PHP_OS_FAMILY === 'Linux') {
            $uptimeOutput = @file_get_contents('/proc/uptime');
            if ($uptimeOutput) {
                $uptime = (int) explode(' ', $uptimeOutput)[0];
            }
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $output = @shell_exec('sysctl -n kern.boottime | awk \'{print $4}\' | tr -d \',\'');
            if ($output !== null) {
                $bootTime = (int) trim($output);
                $uptime = time() - $bootTime;
            }
        }
        
        return [
            'seconds' => $uptime,
            'formatted' => $this->formatUptime($uptime),
        ];
    }

    private function getProcessCount()
    {
        $count = 0;
        
        if (PHP_OS_FAMILY === 'Linux' || PHP_OS_FAMILY === 'Darwin') {
            $output = @shell_exec('ps aux | wc -l');
            if ($output !== null) {
                $count = (int) trim($output) - 1; // Subtract header line
            }
        }
        
        return max($count, 0);
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    private function formatUptime($seconds)
    {
        if ($seconds === 0) {
            return 'Unknown';
        }
        
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        $parts = [];
        
        if ($days > 0) {
            $parts[] = $days . ' day' . ($days > 1 ? 's' : '');
        }
        if ($hours > 0) {
            $parts[] = $hours . ' hour' . ($hours > 1 ? 's' : '');
        }
        if ($minutes > 0 || empty($parts)) {
            $parts[] = $minutes . ' minute' . ($minutes > 1 ? 's' : '');
        }
        
        return implode(', ', $parts);
    }
}