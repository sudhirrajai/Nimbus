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
     * Get historical resource usage (24h, 7d, 30d) with peak analysis
     */
    public function getHistory(Request $request)
    {
        try {
            $range = $request->query('range', '24h');

            $startTime = match ($range) {
                '7d' => now()->subDays(7),
                '30d' => now()->subDays(30),
                default => now()->subHours(24),
            };

            $metrics = \App\Models\ServerMetric::where('created_at', '>=', $startTime)
                ->orderBy('created_at', 'asc')
                ->get();

            if ($metrics->isEmpty()) {
                // If table is newly created, generate one baseline snapshot now
                \Illuminate\Support\Facades\Artisan::call('nimbus:collect-metrics');
                $metrics = \App\Models\ServerMetric::where('created_at', '>=', $startTime)
                    ->orderBy('created_at', 'asc')
                    ->get();
            }

            // Resolve panel timezone for localized graph display
            $panelTimezone = 'Asia/Kolkata';
            try {
                $dbTz = \App\Models\Setting::where('key', 'timezone')->value('value');
                if (!empty($dbTz)) {
                    $panelTimezone = $dbTz;
                }
            } catch (\Exception $e) {
                // fallback
            }

            // Downsample / group points based on range to optimize frontend chart rendering
            $points = [];
            $groupMinutes = match ($range) {
                '7d' => 60,   // 1-hour slots for 7 days = ~168 points
                '30d' => 240, // 4-hour slots for 30 days = ~180 points
                default => 5, // 5-minute slots for 24h = ~288 points
            };

            if ($groupMinutes === 5) {
                foreach ($metrics as $m) {
                    $locTime = $m->created_at->copy()->setTimezone($panelTimezone);
                    $points[] = [
                        'time' => $locTime->format('H:i'),
                        'full_time' => $locTime->format('M d, H:i'),
                        'timestamp' => $m->created_at->timestamp,
                        'cpu' => $m->cpu_percent,
                        'memory' => $m->memory_percent,
                        'memory_used_mb' => $m->memory_used_mb,
                        'memory_total_mb' => $m->memory_total_mb,
                        'load' => $m->load_1min,
                        'disk' => $m->disk_percent,
                    ];
                }
            } else {
                // Group by bucket
                $buckets = [];
                foreach ($metrics as $m) {
                    $bucketKey = floor($m->created_at->timestamp / ($groupMinutes * 60));
                    $buckets[$bucketKey][] = $m;
                }

                foreach ($buckets as $bKey => $bMetrics) {
                    $count = count($bMetrics);
                    $cpuAvg = round(array_sum(array_column($bMetrics, 'cpu_percent')) / $count, 1);
                    $memAvg = round(array_sum(array_column($bMetrics, 'memory_percent')) / $count, 1);
                    $loadAvg = round(array_sum(array_column($bMetrics, 'load_1min')) / $count, 2);
                    $diskAvg = round(array_sum(array_column($bMetrics, 'disk_percent')) / $count, 1);
                    $usedMb = round(array_sum(array_column($bMetrics, 'memory_used_mb')) / $count, 0);
                    $totalMb = $bMetrics[0]->memory_total_mb;

                    $firstTime = $bMetrics[0]->created_at->copy()->setTimezone($panelTimezone);
                    $points[] = [
                        'time' => $firstTime->format('M d, H:i'),
                        'full_time' => $firstTime->format('M d, Y H:i'),
                        'timestamp' => $bMetrics[0]->created_at->timestamp,
                        'cpu' => $cpuAvg,
                        'memory' => $memAvg,
                        'memory_used_mb' => $usedMb,
                        'memory_total_mb' => $totalMb,
                        'load' => $loadAvg,
                        'disk' => $diskAvg,
                    ];
                }
            }

            // Calculate peak values and identify responsible process
            $peakCpuMetric = $metrics->sortByDesc('cpu_percent')->first();
            $peakMemMetric = $metrics->sortByDesc('memory_percent')->first();

            $peakCpu = null;
            if ($peakCpuMetric) {
                $topProc = !empty($peakCpuMetric->top_processes[0]) ? $peakCpuMetric->top_processes[0] : null;
                $peakCpuLoc = $peakCpuMetric->created_at->copy()->setTimezone($panelTimezone);
                $peakCpu = [
                    'value' => $peakCpuMetric->cpu_percent,
                    'time' => $peakCpuLoc->format('M d, H:i'),
                    'full_time' => $peakCpuLoc->format('M d, Y H:i:s'),
                    'process' => $topProc ? ($topProc['command'] ?? $topProc['user'] ?? 'system') : 'N/A',
                    'user' => $topProc['user'] ?? 'N/A',
                ];
            }

            $peakMem = null;
            if ($peakMemMetric) {
                $topProc = !empty($peakMemMetric->top_processes[0]) ? $peakMemMetric->top_processes[0] : null;
                $peakMemLoc = $peakMemMetric->created_at->copy()->setTimezone($panelTimezone);
                $peakMem = [
                    'value' => $peakMemMetric->memory_percent,
                    'used_mb' => $peakMemMetric->memory_used_mb,
                    'time' => $peakMemLoc->format('M d, H:i'),
                    'full_time' => $peakMemLoc->format('M d, Y H:i:s'),
                    'process' => $topProc ? ($topProc['command'] ?? $topProc['user'] ?? 'system') : 'N/A',
                    'user' => $topProc['user'] ?? 'N/A',
                ];
            }

            // Find high-usage incidents (CPU > 80% or RAM > 85%)
            $incidents = [];
            $incidentRows = $metrics->where('is_alert_level', true)->sortByDesc('created_at')->take(10);
            foreach ($incidentRows as $row) {
                $topProc = !empty($row->top_processes[0]) ? $row->top_processes[0] : null;
                $incidents[] = [
                    'time' => $row->created_at->copy()->setTimezone($panelTimezone)->format('M d, Y H:i'),
                    'cpu' => $row->cpu_percent,
                    'memory' => $row->memory_percent,
                    'load' => $row->load_1min,
                    'process' => $topProc ? ($topProc['command'] ?? 'Unknown') : 'System',
                    'user' => $topProc['user'] ?? 'Unknown',
                ];
            }

            // Summary stats
            $avgCpu = $metrics->count() > 0 ? round($metrics->avg('cpu_percent'), 1) : 0;
            $avgMem = $metrics->count() > 0 ? round($metrics->avg('memory_percent'), 1) : 0;
            $avgLoad = $metrics->count() > 0 ? round($metrics->avg('load_1min'), 2) : 0;

            return response()->json([
                'success' => true,
                'range' => $range,
                'points_count' => count($points),
                'summary' => [
                    'avg_cpu' => $avgCpu,
                    'avg_memory' => $avgMem,
                    'avg_load' => $avgLoad,
                    'peak_cpu' => $peakCpu,
                    'peak_memory' => $peakMem,
                    'total_incidents' => $metrics->where('is_alert_level', true)->count(),
                ],
                'points' => $points,
                'incidents' => $incidents,
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
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : [0.1, 0.1, 0.1];
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
