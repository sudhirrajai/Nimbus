<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        \Illuminate\Support\Facades\Log::info("Dashboard index hit");
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
        $cpuData = $this->getCpuUsage();
        $memoryData = $this->getMemoryUsage();
        $diskData = $this->getDiskUsage();
        $loadData = $this->getLoadAverage();
        $uptimeData = $this->getUptime();
        $processCount = $this->getProcessCount();

        $postfixInstalled = file_exists('/etc/postfix/main.cf');
        $dovecotInstalled = file_exists('/etc/dovecot/dovecot.conf');

        return [
            'cpu' => $cpuData,
            'memory' => $memoryData,
            'disk' => $diskData,
            'load' => $loadData,
            'uptime' => $uptimeData,
            'processes' => $processCount,
            'mailServerInstalled' => $postfixInstalled && $dovecotInstalled,
        ];
    }

    /**
     * Get real CPU usage percentage and load stats
     */
    private function getCpuUsage()
    {
        $load = $this->getRawLoadAverage();
        $cores = $this->getCpuCores();
        $usage = 0.0;

        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/stat')) {
            $prevStat = Cache::get('server_prev_cpu_stat');
            $currentStat = @file_get_contents('/proc/stat');

            if ($currentStat) {
                if (!$prevStat) {
                    // First hit: brief 50ms differential sample for instant accurate reading
                    usleep(50000);
                    $stat2 = @file_get_contents('/proc/stat');
                    if ($stat2) {
                        $usage = $this->calculateCpuUsageFromStats($currentStat, $stat2);
                        Cache::put('server_prev_cpu_stat', $stat2, 30);
                    }
                } else {
                    $usage = $this->calculateCpuUsageFromStats($prevStat, $currentStat);
                    Cache::put('server_prev_cpu_stat', $currentStat, 30);
                }
            }
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $output = @shell_exec("top -l 1 -n 0 | grep 'CPU usage'");
            if ($output && preg_match('/(\d+\.?\d*)%\s+idle/', $output, $matches)) {
                $usage = max(0.0, 100.0 - (float) $matches[1]);
            }
        } elseif (PHP_OS_FAMILY === 'Windows') {
            $output = @shell_exec('wmic cpu get loadpercentage 2>nul');
            if ($output && preg_match('/\d+/', $output, $matches)) {
                $usage = (float) $matches[0];
            }
        }

        return [
            'usage' => round($usage, 2),
            'cores' => $cores,
            'load_1min' => round($load[0], 2),
            'load_5min' => round($load[1], 2),
            'load_15min' => round($load[2], 2),
        ];
    }

    /**
     * Calculate CPU usage percentage from two /proc/stat readings
     */
    private function calculateCpuUsageFromStats($stat1, $stat2)
    {
        $info1 = $this->parseCpuStatLine($stat1);
        $info2 = $this->parseCpuStatLine($stat2);

        if (!$info1 || !$info2) {
            return 0.0;
        }

        $totalDiff = $info2['total'] - $info1['total'];
        $idleDiff = $info2['idle'] - $info1['idle'];

        if ($totalDiff <= 0) {
            return 0.0;
        }

        $cpuUsage = 100.0 * (1.0 - ($idleDiff / $totalDiff));
        return max(0.0, min(100.0, $cpuUsage));
    }

    private function parseCpuStatLine($stat)
    {
        $lines = explode("\n", trim($stat));
        if (empty($lines[0])) {
            return null;
        }

        $cpu = preg_split('/\s+/', trim(preg_replace('/^cpu\s+/', '', $lines[0])));
        if (count($cpu) < 4) {
            return null;
        }

        $user = (int) ($cpu[0] ?? 0);
        $nice = (int) ($cpu[1] ?? 0);
        $system = (int) ($cpu[2] ?? 0);
        $idle = (int) ($cpu[3] ?? 0);
        $iowait = (int) ($cpu[4] ?? 0);
        $irq = (int) ($cpu[5] ?? 0);
        $softirq = (int) ($cpu[6] ?? 0);
        $steal = (int) ($cpu[7] ?? 0);

        $idleAll = $idle + $iowait;
        $totalAll = $user + $nice + $system + $idle + $iowait + $irq + $softirq + $steal;

        return [
            'idle' => $idleAll,
            'total' => $totalAll
        ];
    }

    private function getRawLoadAverage()
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            if (is_array($load) && count($load) >= 3) {
                return $load;
            }
        }

        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/loadavg')) {
            $loadContent = @file_get_contents('/proc/loadavg');
            if ($loadContent) {
                $parts = explode(' ', trim($loadContent));
                if (count($parts) >= 3) {
                    return [(float)$parts[0], (float)$parts[1], (float)$parts[2]];
                }
            }
        }

        return [0.0, 0.0, 0.0];
    }

    private function getCpuCores()
    {
        $cores = 1;
        
        if (PHP_OS_FAMILY === 'Linux') {
            $output = @shell_exec('nproc 2>/dev/null');
            if ($output !== null && $output !== false && trim($output) !== '') {
                $cores = (int) trim($output);
            }
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $output = @shell_exec('sysctl -n hw.ncpu 2>/dev/null');
            if ($output !== null && $output !== false && trim($output) !== '') {
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
        
        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/meminfo')) {
            $meminfo = @file_get_contents('/proc/meminfo');
            
            if ($meminfo) {
                preg_match('/MemTotal:\s+(\d+)/', $meminfo, $matchTotal);
                preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $matchAvailable);
                
                if (isset($matchTotal[1])) {
                    $total = (int)$matchTotal[1] * 1024; // Convert KB to bytes
                    if (isset($matchAvailable[1])) {
                        $available = (int)$matchAvailable[1] * 1024;
                    } else {
                        // Fallback for older Linux kernels
                        preg_match('/MemFree:\s+(\d+)/', $meminfo, $matchFree);
                        preg_match('/Buffers:\s+(\d+)/', $meminfo, $matchBuffers);
                        preg_match('/Cached:\s+(\d+)/', $meminfo, $matchCached);
                        $freeVal = (int)($matchFree[1] ?? 0);
                        $bufVal = (int)($matchBuffers[1] ?? 0);
                        $cacheVal = (int)($matchCached[1] ?? 0);
                        $available = ($freeVal + $bufVal + $cacheVal) * 1024;
                    }
                    $used = max(0, $total - $available);
                    $free = max(0, $available);
                }
            }
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $output = @shell_exec('vm_stat 2>/dev/null | grep "Pages free" | awk \'{print $3}\' | tr -d \'.\'');
            $pageSize = 4096;
            if ($output !== null && $output !== false) {
                $free = (int) trim($output) * $pageSize;
            }
            
            $totalOutput = @shell_exec('sysctl -n hw.memsize 2>/dev/null');
            if ($totalOutput !== null && $totalOutput !== false) {
                $total = (int) trim($totalOutput);
                $used = max(0, $total - $free);
            }
        } elseif (PHP_OS_FAMILY === 'Windows') {
            $output = @shell_exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value 2>nul');
            if ($output) {
                preg_match('/TotalVisibleMemorySize=(\d+)/', $output, $totalMatch);
                preg_match('/FreePhysicalMemory=(\d+)/', $output, $freeMatch);
                if (isset($totalMatch[1])) {
                    $total = (int)$totalMatch[1] * 1024;
                    $free = isset($freeMatch[1]) ? (int)$freeMatch[1] * 1024 : 0;
                    $used = max(0, $total - $free);
                }
            }
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
        $rootPath = PHP_OS_FAMILY === 'Windows' ? 'C:' : '/';
        
        $total = @disk_total_space($rootPath);
        $free = @disk_free_space($rootPath);
        
        if ($total === false || $free === false || $total <= 0) {
            $total = 0;
            $free = 0;
            $used = 0;
        } else {
            $used = max(0, $total - $free);
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

    private function getLoadAverage()
    {
        $load = $this->getRawLoadAverage();
        
        return [
            '1min' => round($load[0], 2),
            '5min' => round($load[1], 2),
            '15min' => round($load[2], 2),
        ];
    }

    private function getUptime()
    {
        $uptime = 0;
        
        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/uptime')) {
            $uptimeOutput = @file_get_contents('/proc/uptime');
            if ($uptimeOutput) {
                $uptime = (int) explode(' ', $uptimeOutput)[0];
            }
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $output = @shell_exec('sysctl -n kern.boottime 2>/dev/null | awk \'{print $4}\' | tr -d \',\'');
            if ($output !== null && $output !== false) {
                $bootTime = (int) trim($output);
                $uptime = time() - $bootTime;
            }
        } elseif (PHP_OS_FAMILY === 'Windows') {
            $output = @shell_exec('net statistics workstation 2>nul');
        }
        
        return [
            'seconds' => $uptime,
            'formatted' => $this->formatUptime($uptime),
        ];
    }

    private function getProcessCount()
    {
        $count = 0;
        
        if (PHP_OS_FAMILY === 'Linux') {
            $procDirs = @glob('/proc/[0-9]*', GLOB_ONLYDIR);
            if ($procDirs !== false && count($procDirs) > 0) {
                $count = count($procDirs);
            } else {
                $output = @shell_exec('ps -e --no-headers 2>/dev/null | wc -l');
                if ($output !== null && $output !== false) {
                    $count = (int) trim($output);
                }
            }
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $output = @shell_exec('ps -A -o pid= 2>/dev/null | wc -l');
            if ($output !== null && $output !== false) {
                $count = (int) trim($output);
            }
        } elseif (PHP_OS_FAMILY === 'Windows') {
            $output = @shell_exec('tasklist /NH 2>nul | find /c /v ""');
            if ($output !== null && $output !== false) {
                $count = (int) trim($output);
            }
        }
        
        return max($count, 0);
    }

    private function formatBytes($bytes, $precision = 2)
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    private function formatUptime($seconds)
    {
        if ($seconds <= 0) {
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