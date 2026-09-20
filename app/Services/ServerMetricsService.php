<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class ServerMetricsService
{
    /**
     * Cache key constants
     */
    const CACHE_CPU_READING = 'nimbus_metric_cpu_reading';
    const CACHE_CPU_SNAPSHOT = 'nimbus_metric_cpu_snapshot';
    const CACHE_CPU_HARDWARE = 'nimbus_metric_cpu_hardware';

    /**
     * Get high-accuracy CPU usage and system load
     * Shared across Dashboard, Resources page, and CLI background tasks.
     *
     * @param bool $forceFresh If true, bypasses the 3-second cache
     * @return array
     */
    public static function getCpuUsage(bool $forceFresh = false): array
    {
        if (!$forceFresh && Cache::has(self::CACHE_CPU_READING)) {
            $cached = Cache::get(self::CACHE_CPU_READING);
            if (is_array($cached) && isset($cached['usage'])) {
                return $cached;
            }
        }

        $hardware = self::getCpuHardware();
        $load = self::getLoadAverage();
        $usage = 0.0;

        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/stat')) {
            $currentStat = @file_get_contents('/proc/stat');
            $currentTime = microtime(true);

            if ($currentStat) {
                $curr = self::parseProcStat($currentStat);
                $prevSnapshot = Cache::get(self::CACHE_CPU_SNAPSHOT);

                if ($prevSnapshot && is_array($prevSnapshot) && isset($prevSnapshot['total'])) {
                    $elapsed = $currentTime - ($prevSnapshot['time'] ?? 0);

                    // If snapshot was taken between 0.4s and 30s ago, calculate delta
                    if ($elapsed >= 0.4 && $elapsed <= 30.0) {
                        $totalDiff = $curr['total'] - $prevSnapshot['total'];
                        $idleDiff = $curr['idle'] - $prevSnapshot['idle'];

                        if ($totalDiff > 0) {
                            $usage = 100.0 * (1.0 - ($idleDiff / $totalDiff));
                            $usage = max(0.0, min(100.0, round($usage, 1)));
                        }
                    }
                }

                // If no valid snapshot or elapsed window was too old/narrow, take a 200ms sample
                if ($usage === 0.0 && (!$prevSnapshot || ($currentTime - ($prevSnapshot['time'] ?? 0)) > 30.0)) {
                    usleep(200000); // 200ms clean sample
                    $sampleStat = @file_get_contents('/proc/stat');
                    if ($sampleStat) {
                        $sampleCurr = self::parseProcStat($sampleStat);
                        $totalDiff = $sampleCurr['total'] - $curr['total'];
                        $idleDiff = $sampleCurr['idle'] - $curr['idle'];

                        if ($totalDiff > 0) {
                            $usage = 100.0 * (1.0 - ($idleDiff / $totalDiff));
                            $usage = max(0.0, min(100.0, round($usage, 1)));
                        }
                        $curr = $sampleCurr;
                    }
                }

                // Save snapshot for subsequent requests
                Cache::put(self::CACHE_CPU_SNAPSHOT, [
                    'total' => $curr['total'],
                    'idle' => $curr['idle'],
                    'time' => microtime(true),
                ], 60);
            }
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $output = @shell_exec("top -l 1 -n 0 | grep 'CPU usage'");
            if ($output && preg_match('/(\d+\.?\d*)%\s+idle/', $output, $matches)) {
                $usage = max(0.0, min(100.0, round(100.0 - (float) $matches[1], 1)));
            }
        } elseif (PHP_OS_FAMILY === 'Windows') {
            $output = @shell_exec('wmic cpu get loadpercentage 2>nul');
            if ($output && preg_match('/\d+/', $output, $matches)) {
                $usage = round((float) $matches[0], 1);
            }
        }

        $result = [
            'usage' => $usage,
            'cores' => $hardware['cores'],
            'model' => $hardware['model'],
            'load_1min' => $load['1min'],
            'load_5min' => $load['5min'],
            'load_15min' => $load['15min'],
        ];

        // Cache for 3 seconds so concurrent or rapid requests across pages receive identical values
        Cache::put(self::CACHE_CPU_READING, $result, 3);

        return $result;
    }

    /**
     * Parse /proc/stat CPU tick counters
     */
    private static function parseProcStat(string $stat): array
    {
        $lines = explode("\n", trim($stat));
        $firstLine = trim($lines[0] ?? '');
        $parts = preg_split('/\s+/', $firstLine);

        // parts[0] is 'cpu', parts[1] is user, parts[2] is nice, parts[3] is system, parts[4] is idle
        $user = (int) ($parts[1] ?? 0);
        $nice = (int) ($parts[2] ?? 0);
        $system = (int) ($parts[3] ?? 0);
        $idle = (int) ($parts[4] ?? 0);
        $iowait = (int) ($parts[5] ?? 0);
        $irq = (int) ($parts[6] ?? 0);
        $softirq = (int) ($parts[7] ?? 0);
        $steal = (int) ($parts[8] ?? 0);

        $total = $user + $nice + $system + $idle + $iowait + $irq + $softirq + $steal;

        return [
            'idle' => $idle + $iowait,
            'total' => $total,
        ];
    }

    /**
     * Get CPU hardware information (cached permanently)
     */
    public static function getCpuHardware(): array
    {
        return Cache::rememberForever(self::CACHE_CPU_HARDWARE, function () {
            $cores = 1;
            $model = 'Unknown';

            if (PHP_OS_FAMILY === 'Linux') {
                $nproc = @shell_exec('nproc 2>/dev/null');
                if ($nproc !== null && trim($nproc) !== '') {
                    $cores = max(1, (int) trim($nproc));
                }

                $cpuinfo = @file_get_contents('/proc/cpuinfo');
                if ($cpuinfo && preg_match('/model name\s*:\s*(.+)/i', $cpuinfo, $matches)) {
                    $model = trim($matches[1]);
                }
            } elseif (PHP_OS_FAMILY === 'Darwin') {
                $output = @shell_exec('sysctl -n hw.ncpu 2>/dev/null');
                if ($output) {
                    $cores = max(1, (int) trim($output));
                }
                $modelOut = @shell_exec('sysctl -n machdep.cpu.brand_string 2>/dev/null');
                if ($modelOut) {
                    $model = trim($modelOut);
                }
            } elseif (PHP_OS_FAMILY === 'Windows') {
                $cores = max(1, (int) (getenv('NUMBER_OF_PROCESSORS') ?: 1));
                $model = trim(getenv('PROCESSOR_IDENTIFIER') ?: 'Windows Processor');
            }

            return [
                'cores' => $cores,
                'model' => $model,
            ];
        });
    }

    /**
     * Get system memory usage
     */
    public static function getMemoryUsage(): array
    {
        $free = 0;
        $total = 0;
        $used = 0;
        $swapTotal = 0;
        $swapFree = 0;

        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/meminfo')) {
            $meminfo = @file_get_contents('/proc/meminfo');
            if ($meminfo) {
                $lines = explode("\n", $meminfo);
                $mem = [];
                foreach ($lines as $line) {
                    if (preg_match('/^(\w+):\s+(\d+)\s+kB/', $line, $matches)) {
                        $mem[$matches[1]] = (int) $matches[2] * 1024;
                    }
                }

                $total = $mem['MemTotal'] ?? 0;
                $available = $mem['MemAvailable'] ?? ($mem['MemFree'] ?? 0);
                $used = max(0, $total - $available);
                $free = max(0, $available);

                $swapTotal = $mem['SwapTotal'] ?? 0;
                $swapFree = $mem['SwapFree'] ?? 0;
            }
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $pageSize = 4096;
            $freePages = @shell_exec('vm_stat 2>/dev/null | grep "Pages free" | awk \'{print $3}\' | tr -d \'.\'');
            if ($freePages !== null) {
                $free = (int) trim($freePages) * $pageSize;
            }
            $totalBytes = @shell_exec('sysctl -n hw.memsize 2>/dev/null');
            if ($totalBytes !== null) {
                $total = (int) trim($totalBytes);
                $used = max(0, $total - $free);
            }
        } elseif (PHP_OS_FAMILY === 'Windows') {
            $output = @shell_exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value 2>nul');
            if ($output) {
                preg_match('/TotalVisibleMemorySize=(\d+)/', $output, $totalMatch);
                preg_match('/FreePhysicalMemory=(\d+)/', $output, $freeMatch);
                if (isset($totalMatch[1])) {
                    $total = (int) $totalMatch[1] * 1024;
                    $free = isset($freeMatch[1]) ? (int) $freeMatch[1] * 1024 : 0;
                    $used = max(0, $total - $free);
                }
            }
        }

        $swapUsed = max(0, $swapTotal - $swapFree);
        $percent = $total > 0 ? round(($used / $total) * 100, 1) : 0.0;
        $swapPercent = $swapTotal > 0 ? round(($swapUsed / $swapTotal) * 100, 1) : 0.0;

        return [
            'total' => self::formatBytes($total),
            'used' => self::formatBytes($used),
            'free' => self::formatBytes($free),
            'percentage' => $percent,
            'usage_percent' => $percent,
            'total_bytes' => $total,
            'used_bytes' => $used,
            'total_mb' => round($total / (1024 * 1024), 1),
            'used_mb' => round($used / (1024 * 1024), 1),
            'swap_total' => self::formatBytes($swapTotal),
            'swap_used' => self::formatBytes($swapUsed),
            'swap_total_mb' => round($swapTotal / (1024 * 1024), 1),
            'swap_used_mb' => round($swapUsed / (1024 * 1024), 1),
            'swap_percent' => $swapPercent,
        ];
    }

    /**
     * Get system load average
     */
    public static function getLoadAverage(): array
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            if (is_array($load) && count($load) >= 3) {
                return [
                    '1min' => round((float) $load[0], 2),
                    '5min' => round((float) $load[1], 2),
                    '15min' => round((float) $load[2], 2),
                ];
            }
        }

        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/loadavg')) {
            $loadContent = @file_get_contents('/proc/loadavg');
            if ($loadContent) {
                $parts = explode(' ', trim($loadContent));
                if (count($parts) >= 3) {
                    return [
                        '1min' => round((float) $parts[0], 2),
                        '5min' => round((float) $parts[1], 2),
                        '15min' => round((float) $parts[2], 2),
                    ];
                }
            }
        }

        return ['1min' => 0.0, '5min' => 0.0, '15min' => 0.0];
    }

    /**
     * Get disk usage breakdown
     */
    public static function getDiskUsage(): array
    {
        $disks = [];

        if (PHP_OS_FAMILY === 'Linux' || PHP_OS_FAMILY === 'Darwin') {
            $output = [];
            @exec("df -P -k 2>/dev/null | grep -E '^/dev/'", $output);

            foreach ($output as $line) {
                $parts = preg_split('/\s+/', $line);
                if (count($parts) >= 6) {
                    $total = (float) $parts[1] * 1024;
                    $used = (float) $parts[2] * 1024;
                    $free = (float) $parts[3] * 1024;
                    $percent = (int) str_replace('%', '', $parts[4]);
                    $mount = $parts[5];

                    $disks[] = [
                        'filesystem' => $parts[0],
                        'mount' => $mount,
                        'total' => self::formatBytes($total),
                        'used' => self::formatBytes($used),
                        'free' => self::formatBytes($free),
                        'percentage' => $percent,
                        'total_bytes' => $total,
                        'used_bytes' => $used,
                    ];
                }
            }
        }

        if (empty($disks)) {
            $total = @disk_total_space('/') ?: 1;
            $free = @disk_free_space('/') ?: 0;
            $used = max(0, $total - $free);
            $disks[] = [
                'filesystem' => '/',
                'mount' => '/',
                'total' => self::formatBytes($total),
                'used' => self::formatBytes($used),
                'free' => self::formatBytes($free),
                'percentage' => round(($used / $total) * 100, 1),
                'total_bytes' => $total,
                'used_bytes' => $used,
            ];
        }

        return $disks;
    }

    /**
     * Get system uptime
     */
    public static function getUptime(): array
    {
        $totalSeconds = 0;

        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/uptime')) {
            $uptimeContent = @file_get_contents('/proc/uptime');
            if ($uptimeContent) {
                $parts = explode(' ', trim($uptimeContent));
                $totalSeconds = (int) floor((float) $parts[0]);
            }
        } elseif (PHP_OS_FAMILY === 'Windows') {
            $output = @shell_exec('net statistics workstation 2>nul');
            $totalSeconds = 86400; // fallback
        }

        $days = (int) floor($totalSeconds / 86400);
        $hours = (int) floor(($totalSeconds % 86400) / 3600);
        $minutes = (int) floor(($totalSeconds % 3600) / 60);

        $formatted = "{$days}d {$hours}h {$minutes}m";

        return [
            'total_seconds' => $totalSeconds,
            'days' => $days,
            'hours' => $hours,
            'minutes' => $minutes,
            'formatted' => $formatted,
        ];
    }

    /**
     * Get top running processes by CPU
     */
    public static function getTopProcesses(int $limit = 10): array
    {
        $processes = [];

        if (PHP_OS_FAMILY === 'Linux') {
            $output = [];
            @exec("ps -eo pid,user,%cpu,%mem,comm --sort=-%cpu | head -" . ($limit + 1) . " 2>/dev/null", $output);

            foreach (array_slice($output, 1) as $line) {
                $parts = preg_split('/\s+/', trim($line), 5);
                if (count($parts) >= 5) {
                    $processes[] = [
                        'pid' => $parts[0],
                        'user' => $parts[1],
                        'cpu' => (float) $parts[2],
                        'memory' => (float) $parts[3],
                        'command' => $parts[4],
                    ];
                }
            }
        }

        return $processes;
    }

    /**
     * Get network statistics (bytes in/out)
     */
    public static function getNetworkStats(): array
    {
        $interfaces = [];

        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/net/dev')) {
            $content = @file_get_contents('/proc/net/dev');
            if ($content) {
                $lines = explode("\n", $content);
                foreach (array_slice($lines, 2) as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;

                    $parts = preg_split('/[:\s]+/', $line);
                    $iface = $parts[0];
                    if ($iface === 'lo') continue; // ignore loopback

                    $rxBytes = (float) ($parts[1] ?? 0);
                    $txBytes = (float) ($parts[9] ?? 0);

                    $interfaces[] = [
                        'interface' => $iface,
                        'rx' => self::formatBytes($rxBytes),
                        'tx' => self::formatBytes($txBytes),
                        'rx_bytes' => $rxBytes,
                        'tx_bytes' => $txBytes,
                    ];
                }
            }
        }

        return $interfaces;
    }

    /**
     * Calculate statistical percentile (e.g. 95th percentile P95)
     */
    public static function calculatePercentile(array $values, float $percentile): float
    {
        if (empty($values)) {
            return 0.0;
        }

        sort($values);
        $count = count($values);
        if ($count === 1) {
            return round((float) $values[0], 1);
        }

        $index = ($percentile / 100.0) * ($count - 1);
        $floorIndex = (int) floor($index);
        $ceilIndex = (int) ceil($index);

        if ($floorIndex === $ceilIndex) {
            return round((float) $values[$floorIndex], 1);
        }

        $fraction = $index - $floorIndex;
        $val = $values[$floorIndex] + $fraction * ($values[$ceilIndex] - $values[$floorIndex]);

        return round((float) $val, 1);
    }

    /**
     * Helper to format bytes into human readable format
     */
    public static function formatBytes($bytes, int $precision = 2): string
    {
        $bytes = (float) $bytes;
        if ($bytes <= 0) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $power = floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        return round($bytes / pow(1024, $power), $precision) . ' ' . $units[$power];
    }
}
