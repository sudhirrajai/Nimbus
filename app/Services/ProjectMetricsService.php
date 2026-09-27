<?php

namespace App\Services;

use App\Models\ProjectMetric;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class ProjectMetricsService
{
    const CACHE_DISK_PREFIX = 'nimbus_project_disk_mb_';
    const CACHE_DISK_TTL = 14400; // 4 hours

    /**
     * Get real-time resource utilization for all projects in /var/www
     * Attributing processes via Linux user, process command, and PHP-FPM pool.
     */
    public static function getRealtimeProjectUsage(): array
    {
        $basePath = '/var/www';
        $projects = [];
        $userToDomain = [];

        if (PHP_OS_FAMILY === 'Linux' && is_dir($basePath)) {
            $dirs = @glob("{$basePath}/*") ?: [];
            $ignoreList = ['.', '..', 'html', '.cache', '.config', '.local', '.npm', '.ssh', '.wp-cli', '000-nimbus', 'nimbus_panel'];

            foreach ($dirs as $dir) {
                if (!is_dir($dir)) continue;
                $domain = basename($dir);
                if (in_array($domain, $ignoreList)) continue;

                $owner = 'www-data';
                if (function_exists('posix_getpwuid')) {
                    $stat = @stat($dir);
                    if ($stat && isset($stat['uid'])) {
                        $pw = @posix_getpwuid($stat['uid']);
                        if ($pw && !empty($pw['name'])) {
                            $owner = $pw['name'];
                        }
                    }
                }

                $diskMb = self::getCachedProjectDiskMb($domain, $dir);

                $projects[$domain] = [
                    'domain' => $domain,
                    'owner' => $owner,
                    'user' => $owner,
                    'path' => $dir,
                    'dir' => $dir,
                    'cpu_percent' => 0.0,
                    'memory_mb' => 0.0,
                    'memory_percent' => 0.0,
                    'disk_mb' => $diskMb,
                    'disk_formatted' => ServerMetricsService::formatBytes($diskMb * 1024 * 1024),
                    'process_count' => 0,
                    'is_active' => false,
                    'status' => 'idle',
                ];

                if (!in_array($owner, ['www-data', 'root', 'unknown'])) {
                    $userToDomain[$owner] = $domain;
                }
            }

            // Inspect all running processes on the system
            $lines = [];
            @exec("ps -eo user:32,pid,%cpu,%mem,rss,args --no-headers 2>/dev/null", $lines);

            foreach ($lines as $line) {
                $parts = preg_split('/\s+/', trim($line), 6);
                if (count($parts) < 6) continue;

                $user = $parts[0];
                $pid = $parts[1];
                $cpu = (float) $parts[2];
                $memPercent = (float) $parts[3];
                $rssKb = (int) $parts[4];
                $cmd = $parts[5];

                $matchedDomain = null;
                if (isset($userToDomain[$user])) {
                    $matchedDomain = $userToDomain[$user];
                } else {
                    // Check if command line contains /var/www/<domain> or php-fpm: pool <domain>
                    foreach ($projects as $d => $info) {
                        if (str_contains($cmd, $d)) {
                            $matchedDomain = $d;
                            break;
                        }
                    }
                }

                if ($matchedDomain && isset($projects[$matchedDomain])) {
                    $projects[$matchedDomain]['cpu_percent'] += $cpu;
                    $projects[$matchedDomain]['memory_mb'] += ($rssKb / 1024);
                    $projects[$matchedDomain]['memory_percent'] += $memPercent;
                    $projects[$matchedDomain]['process_count']++;
                }
            }

            $suspendedDomains = ProjectControlService::getSuspendedDomains();
            foreach ($projects as $d => &$p) {
                $p['cpu_percent'] = round($p['cpu_percent'], 1);
                $p['memory_mb'] = round($p['memory_mb'], 1);
                $p['memory_percent'] = round($p['memory_percent'], 1);
                $p['is_suspended'] = in_array(strtolower($d), $suspendedDomains, true) || ProjectControlService::isSuspended($d);
                if ($p['is_suspended']) {
                    $p['status'] = 'suspended';
                    $p['is_active'] = false;
                } else {
                    $p['is_active'] = ($p['process_count'] > 0 || $p['cpu_percent'] > 0 || $p['memory_mb'] > 0);
                    $p['status'] = $p['is_active'] ? 'active' : 'idle';
                }
            }
            unset($p);
        }

        // Sort by active status, then RAM descending
        uasort($projects, function ($a, $b) {
            if ($a['is_active'] !== $b['is_active']) {
                return $b['is_active'] <=> $a['is_active'];
            }
            if ($b['memory_mb'] !== $a['memory_mb']) {
                return $b['memory_mb'] <=> $a['memory_mb'];
            }
            return $b['cpu_percent'] <=> $a['cpu_percent'];
        });

        return array_values($projects);
    }

    /**
     * Get summary of all projects with historical average and peak over the chosen range
     *
     * @param string $range '1h', '24h', '7d', '30d'
     * @return array
     */
    public static function getAllProjectsSummary(string $range = '24h'): array
    {
        $realtimeProjects = self::getRealtimeProjectUsage();

        $startTime = match ($range) {
            '1h' => Carbon::now('UTC')->subHour(),
            '7d' => Carbon::now('UTC')->subDays(7),
            '30d' => Carbon::now('UTC')->subDays(30),
            default => Carbon::now('UTC')->subHours(24),
        };

        // Query historical metrics grouped by domain
        $historicalMetrics = ProjectMetric::where('created_at', '>=', $startTime)
            ->get()
            ->groupBy('domain');

        $projectList = [];
        $topCpu = null;
        $topMemory = null;
        $topStorage = null;
        $activeCount = 0;

        foreach ($realtimeProjects as $proj) {
            $domain = $proj['domain'];
            $metrics = $historicalMetrics->get($domain);

            $avgCpu = 0.0;
            $peakCpu = (float) $proj['cpu_percent'];
            $avgMemMb = 0.0;
            $peakMemMb = (float) $proj['memory_mb'];

            if ($metrics && $metrics->isNotEmpty()) {
                $avgCpu = round((float) $metrics->avg('cpu_percent'), 1);
                $peakCpu = max($peakCpu, round((float) $metrics->max('cpu_percent'), 1));
                $avgMemMb = round((float) $metrics->avg('memory_mb'), 1);
                $peakMemMb = max($peakMemMb, round((float) $metrics->max('memory_mb'), 1));
            } else {
                $avgCpu = $proj['cpu_percent'];
                $avgMemMb = $proj['memory_mb'];
            }

            if ($proj['is_active']) {
                $activeCount++;
            }

            $entry = array_merge($proj, [
                'range_avg_cpu' => $avgCpu,
                'range_peak_cpu' => $peakCpu,
                'range_avg_memory_mb' => $avgMemMb,
                'range_avg_mem_mb' => $avgMemMb,
                'range_peak_memory_mb' => $peakMemMb,
                'range_peak_mem_mb' => $peakMemMb,
                'samples_count' => $metrics ? $metrics->count() : 0,
            ]);

            $projectList[] = $entry;

            // Track top consumers (only non-zero consumers to avoid arbitrarily selecting an idle 0 MB site)
            if ($entry['range_peak_cpu'] > 0 && (!$topCpu || $entry['range_peak_cpu'] > ($topCpu['range_peak_cpu'] ?? 0))) {
                $topCpu = $entry;
            }
            if ($entry['range_peak_memory_mb'] > 0 && (!$topMemory || $entry['range_peak_memory_mb'] > ($topMemory['range_peak_memory_mb'] ?? 0))) {
                $topMemory = $entry;
            }
            if ($entry['disk_mb'] > 0 && (!$topStorage || $entry['disk_mb'] > ($topStorage['disk_mb'] ?? 0))) {
                $topStorage = $entry;
            }
        }

        return [
            'range' => $range,
            'total_projects' => count($projectList),
            'active_projects' => $activeCount,
            'top_cpu_project' => $topCpu,
            'top_memory_project' => $topMemory,
            'top_storage_project' => $topStorage,
            'top_disk_project' => $topStorage,
            'projects' => $projectList,
        ];
    }

    /**
     * Get detailed historical time-series data for a specific project
     */
    public static function getProjectHistory(string $domain, string $range = '24h'): array
    {
        // Resolve panel timezone
        $panelTimezone = 'Asia/Kolkata';
        try {
            $dbTz = Setting::where('key', 'timezone')->value('value');
            if (!empty($dbTz)) {
                $panelTimezone = $dbTz;
            }
        } catch (\Throwable $e) {}

        $startTime = match ($range) {
            '1h' => Carbon::now('UTC')->subHour(),
            '7d' => Carbon::now('UTC')->subDays(7),
            '30d' => Carbon::now('UTC')->subDays(30),
            default => Carbon::now('UTC')->subHours(24),
        };

        $metrics = ProjectMetric::where('domain', $domain)
            ->where('created_at', '>=', $startTime)
            ->orderBy('created_at', 'asc')
            ->get();

        $groupMinutes = match ($range) {
            '1h' => 1,
            '7d' => 60,
            '30d' => 240,
            default => 5,
        };

        $points = [];
        if ($groupMinutes <= 5) {
            foreach ($metrics as $m) {
                $locTime = $m->created_at->copy()->setTimezone($panelTimezone);
                $points[] = [
                    'time' => $locTime->format('H:i'),
                    'full_time' => $locTime->format('M d, H:i'),
                    'timestamp' => $m->created_at->timestamp,
                    'cpu' => (float) $m->cpu_percent,
                    'memory_mb' => (float) $m->memory_mb,
                    'memory_percent' => (float) $m->memory_percent,
                    'disk_mb' => (float) $m->disk_mb,
                    'processes' => (int) $m->process_count,
                ];
            }
        } else {
            // Group buckets
            $buckets = [];
            foreach ($metrics as $m) {
                $bucketKey = floor($m->created_at->timestamp / ($groupMinutes * 60));
                $buckets[$bucketKey][] = $m;
            }

            foreach ($buckets as $bMetrics) {
                $count = count($bMetrics);
                $cpuAvg = round(array_sum(array_column($bMetrics, 'cpu_percent')) / $count, 1);
                $memAvg = round(array_sum(array_column($bMetrics, 'memory_mb')) / $count, 1);
                $memPctAvg = round(array_sum(array_column($bMetrics, 'memory_percent')) / $count, 1);
                $diskMb = (float) $bMetrics[0]->disk_mb;
                $procAvg = (int) round(array_sum(array_column($bMetrics, 'process_count')) / $count);

                $firstTime = $bMetrics[0]->created_at->copy()->setTimezone($panelTimezone);
                $points[] = [
                    'time' => $firstTime->format('M d, H:i'),
                    'full_time' => $firstTime->format('M d, Y H:i'),
                    'timestamp' => $bMetrics[0]->created_at->timestamp,
                    'cpu' => $cpuAvg,
                    'memory_mb' => $memAvg,
                    'memory_percent' => $memPctAvg,
                    'disk_mb' => $diskMb,
                    'processes' => $procAvg,
                ];
            }
        }

        // Summary statistics
        $cpuValues = $metrics->pluck('cpu_percent')->filter(fn($v) => is_numeric($v))->map(fn($v) => (float)$v)->all();
        $memValues = $metrics->pluck('memory_mb')->filter(fn($v) => is_numeric($v))->map(fn($v) => (float)$v)->all();
        $procValues = $metrics->pluck('process_count')->filter(fn($v) => is_numeric($v))->map(fn($v) => (int)$v)->all();

        $avgCpu = !empty($cpuValues) ? round(array_sum($cpuValues) / count($cpuValues), 1) : 0.0;
        $peakCpuMetric = $metrics->sortByDesc('cpu_percent')->first();
        $peakCpu = $peakCpuMetric ? [
            'value' => (float) $peakCpuMetric->cpu_percent,
            'time' => $peakCpuMetric->created_at->copy()->setTimezone($panelTimezone)->format('M d, H:i'),
            'full_time' => $peakCpuMetric->created_at->copy()->setTimezone($panelTimezone)->format('M d, Y H:i:s'),
        ] : null;

        $avgMem = !empty($memValues) ? round(array_sum($memValues) / count($memValues), 1) : 0.0;
        $peakMemMetric = $metrics->sortByDesc('memory_mb')->first();
        $peakMem = $peakMemMetric ? [
            'value' => (float) $peakMemMetric->memory_mb,
            'time' => $peakMemMetric->created_at->copy()->setTimezone($panelTimezone)->format('M d, H:i'),
            'full_time' => $peakMemMetric->created_at->copy()->setTimezone($panelTimezone)->format('M d, Y H:i:s'),
        ] : null;

        $avgProc = !empty($procValues) ? (int) round(array_sum($procValues) / count($procValues)) : 0;

        // If no background historical points recorded yet, synthesize current real-time point
        if (empty($points)) {
            $realtimeList = self::getRealtimeProjectUsage();
            foreach ($realtimeList as $rp) {
                if (strtolower($rp['domain']) === strtolower($domain)) {
                    $nowLoc = Carbon::now($panelTimezone);
                    $points[] = [
                        'time' => $nowLoc->format('H:i'),
                        'full_time' => $nowLoc->format('M d, H:i'),
                        'timestamp' => Carbon::now('UTC')->timestamp,
                        'cpu' => (float) $rp['cpu_percent'],
                        'memory_mb' => (float) $rp['memory_mb'],
                        'memory_percent' => (float) $rp['memory_percent'],
                        'disk_mb' => (float) $rp['disk_mb'],
                        'processes' => (int) $rp['process_count'],
                    ];
                    if (empty($cpuValues)) {
                        $avgCpu = (float) $rp['cpu_percent'];
                        if ($rp['cpu_percent'] > 0) {
                            $peakCpu = [
                                'value' => (float) $rp['cpu_percent'],
                                'time' => $nowLoc->format('H:i'),
                                'full_time' => $nowLoc->format('M d, Y H:i:s'),
                            ];
                        }
                    }
                    if (empty($memValues)) {
                        $avgMem = (float) $rp['memory_mb'];
                        if ($rp['memory_mb'] > 0) {
                            $peakMem = [
                                'value' => (float) $rp['memory_mb'],
                                'time' => $nowLoc->format('H:i'),
                                'full_time' => $nowLoc->format('M d, Y H:i:s'),
                            ];
                        }
                    }
                    if (empty($procValues)) {
                        $avgProc = (int) $rp['process_count'];
                    }
                    break;
                }
            }
        }

        $sitePath = "/var/www/{$domain}";
        $currentDiskMb = self::getCachedProjectDiskMb($domain, $sitePath);

        return [
            'domain' => $domain,
            'range' => $range,
            'timezone' => $panelTimezone,
            'current_disk_mb' => $currentDiskMb,
            'current_disk_formatted' => ServerMetricsService::formatBytes($currentDiskMb * 1024 * 1024),
            'summary' => [
                'avg_cpu' => $avgCpu,
                'peak_cpu' => $peakCpu,
                'avg_memory_mb' => $avgMem,
                'peak_memory_mb' => $peakMem,
                'avg_process_count' => $avgProc,
                'total_samples' => count($points),
                'samples_count' => count($points),
            ],
            'points' => $points,
        ];
    }

    /**
     * Collect snapshot of all active/managed projects into project_metrics table
     * Executed by the 5-minute background cron.
     */
    public static function collectSnapshot(): int
    {
        $projects = self::getRealtimeProjectUsage();
        $now = Carbon::now('UTC');
        $insertedCount = 0;

        foreach ($projects as $p) {
            ProjectMetric::create([
                'domain' => $p['domain'],
                'system_user' => $p['owner'] ?? null,
                'cpu_percent' => $p['cpu_percent'] ?? 0.0,
                'memory_mb' => $p['memory_mb'] ?? 0.0,
                'memory_percent' => $p['memory_percent'] ?? 0.0,
                'disk_mb' => $p['disk_mb'] ?? 0.0,
                'process_count' => $p['process_count'] ?? 0,
                'created_at' => $now,
            ]);
            $insertedCount++;
        }

        // Rolling 30-day retention prune
        ProjectMetric::where('created_at', '<', Carbon::now('UTC')->subDays(30))->delete();

        return $insertedCount;
    }

    /**
     * Get or calculate project directory disk storage size (cached for 4 hours to avoid heavy I/O)
     */
    public static function getCachedProjectDiskMb(string $domain, string $dir): float
    {
        $cacheKey = self::CACHE_DISK_PREFIX . md5($domain);

        return Cache::remember($cacheKey, self::CACHE_DISK_TTL, function () use ($dir) {
            if (PHP_OS_FAMILY === 'Linux' && is_dir($dir)) {
                $output = [];
                @exec("du -sm " . escapeshellarg($dir) . " 2>/dev/null", $output);
                if (!empty($output[0])) {
                    $parts = preg_split('/\s+/', trim($output[0]));
                    return (float) ($parts[0] ?? 0.0);
                }
            }
            return 0.0;
        });
    }
}
