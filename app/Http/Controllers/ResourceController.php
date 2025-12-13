<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class ResourceController extends Controller
{
    /**
     * Display resource usage page
     */
    public function index()
    {
        return Inertia::render('Resources/Index');
    }

    /**
     * Get current system resource usage
     */
    public function getUsage()
    {
        try {
            $data = [
                'cpu' => $this->getCpuUsage(),
                'memory' => $this->getMemoryUsage(),
                'disk' => $this->getDiskUsage(),
                'load' => $this->getLoadAverage(),
                'uptime' => $this->getUptime(),
                'network' => $this->getNetworkStats(),
                'processes' => $this->getTopProcesses(),
            ];
            
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get CPU usage percentage
     */
    private function getCpuUsage()
    {
        // Read /proc/stat for CPU info
        $stat1 = file_get_contents('/proc/stat');
        usleep(100000); // 100ms delay
        $stat2 = file_get_contents('/proc/stat');
        
        $info1 = $this->parseCpuStat($stat1);
        $info2 = $this->parseCpuStat($stat2);
        
        $diff = [];
        $diff['total'] = $info2['total'] - $info1['total'];
        $diff['idle'] = $info2['idle'] - $info1['idle'];
        
        if ($diff['total'] == 0) {
            return 0;
        }
        
        $usage = 100 * (1 - $diff['idle'] / $diff['total']);
        
        // Get CPU info
        $cpuInfo = [];
        exec('cat /proc/cpuinfo | grep "model name" | head -1', $output);
        if (!empty($output[0])) {
            $cpuInfo['model'] = trim(str_replace('model name', '', str_replace(':', '', $output[0])));
        }
        exec('nproc', $cores);
        $cpuInfo['cores'] = isset($cores[0]) ? (int)$cores[0] : 1;
        
        return [
            'usage' => round($usage, 1),
            'model' => $cpuInfo['model'] ?? 'Unknown',
            'cores' => $cpuInfo['cores']
        ];
    }

    /**
     * Parse CPU stat line
     */
    private function parseCpuStat($stat)
    {
        $lines = explode("\n", $stat);
        $cpu = explode(" ", preg_replace("/cpu\s+/", "", $lines[0]));
        
        return [
            'user' => $cpu[0],
            'nice' => $cpu[1],
            'system' => $cpu[2],
            'idle' => $cpu[3],
            'iowait' => $cpu[4] ?? 0,
            'irq' => $cpu[5] ?? 0,
            'softirq' => $cpu[6] ?? 0,
            'total' => array_sum($cpu)
        ];
    }

    /**
     * Get memory usage
     */
    private function getMemoryUsage()
    {
        $meminfo = file_get_contents('/proc/meminfo');
        $lines = explode("\n", $meminfo);
        $mem = [];
        
        foreach ($lines as $line) {
            if (preg_match('/^(\w+):\s+(\d+)\s+kB/', $line, $matches)) {
                $mem[$matches[1]] = (int)$matches[2] * 1024; // Convert to bytes
            }
        }
        
        $total = $mem['MemTotal'] ?? 0;
        $free = $mem['MemFree'] ?? 0;
        $available = $mem['MemAvailable'] ?? $free;
        $buffers = $mem['Buffers'] ?? 0;
        $cached = $mem['Cached'] ?? 0;
        
        $used = $total - $available;
        $percentage = $total > 0 ? round(($used / $total) * 100, 1) : 0;
        
        return [
            'total' => $this->formatBytes($total),
            'used' => $this->formatBytes($used),
            'free' => $this->formatBytes($available),
            'percentage' => $percentage,
            'totalBytes' => $total,
            'usedBytes' => $used
        ];
    }

    /**
     * Get disk usage
     */
    private function getDiskUsage()
    {
        $disks = [];
        
        exec("df -B1 / /var/www 2>/dev/null | tail -n +2", $output);
        
        foreach ($output as $line) {
            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 6) {
                $disks[] = [
                    'filesystem' => $parts[0],
                    'total' => $this->formatBytes((int)$parts[1]),
                    'used' => $this->formatBytes((int)$parts[2]),
                    'available' => $this->formatBytes((int)$parts[3]),
                    'percentage' => (int)str_replace('%', '', $parts[4]),
                    'mount' => $parts[5]
                ];
            }
        }
        
        return $disks;
    }

    /**
     * Get system load average
     */
    private function getLoadAverage()
    {
        $load = sys_getloadavg();
        exec('nproc', $cores);
        $numCores = isset($cores[0]) ? (int)$cores[0] : 1;
        
        return [
            '1min' => round($load[0], 2),
            '5min' => round($load[1], 2),
            '15min' => round($load[2], 2),
            'cores' => $numCores,
            'percentage' => round(($load[0] / $numCores) * 100, 1)
        ];
    }

    /**
     * Get system uptime
     */
    private function getUptime()
    {
        $uptime = file_get_contents('/proc/uptime');
        $seconds = (int)explode(' ', $uptime)[0];
        
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        return [
            'seconds' => $seconds,
            'formatted' => "{$days}d {$hours}h {$minutes}m",
            'days' => $days,
            'hours' => $hours,
            'minutes' => $minutes
        ];
    }

    /**
     * Get network statistics
     */
    private function getNetworkStats()
    {
        $stats = [];
        $netDev = file_get_contents('/proc/net/dev');
        $lines = explode("\n", $netDev);
        
        foreach ($lines as $line) {
            if (preg_match('/^\s*(\w+):\s*(.*)$/', $line, $matches)) {
                $interface = $matches[1];
                if ($interface === 'lo') continue; // Skip loopback
                
                $values = preg_split('/\s+/', trim($matches[2]));
                $stats[] = [
                    'interface' => $interface,
                    'rx' => $this->formatBytes((int)$values[0]),
                    'tx' => $this->formatBytes((int)$values[8]),
                    'rxBytes' => (int)$values[0],
                    'txBytes' => (int)$values[8]
                ];
            }
        }
        
        return $stats;
    }

    /**
     * Get top processes by CPU/Memory
     */
    private function getTopProcesses()
    {
        $processes = [];
        exec("ps aux --sort=-%cpu | head -11 | tail -10", $output);
        
        foreach ($output as $line) {
            $parts = preg_split('/\s+/', $line, 11);
            if (count($parts) >= 11) {
                $processes[] = [
                    'user' => $parts[0],
                    'pid' => $parts[1],
                    'cpu' => $parts[2],
                    'memory' => $parts[3],
                    'command' => substr($parts[10], 0, 50)
                ];
            }
        }
        
        return $processes;
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
