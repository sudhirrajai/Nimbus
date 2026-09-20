<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ServerMetric;
use App\Models\Setting;
use App\Services\ServerMetricsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;

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
     * Uses ServerMetricsService for unified accuracy across the panel.
     */
    public function getUsage()
    {
        try {
            $data = [
                'cpu' => ServerMetricsService::getCpuUsage(),
                'memory' => ServerMetricsService::getMemoryUsage(),
                'disk' => ServerMetricsService::getDiskUsage(),
                'load' => ServerMetricsService::getLoadAverage(),
                'uptime' => ServerMetricsService::getUptime(),
                'network' => ServerMetricsService::getNetworkStats(),
                'processes' => ServerMetricsService::getTopProcesses(10),
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get historical resource usage with AWS-grade reporting
     * Supports:
     * - '1h': Last 1 Hour
     * - '24h': Last 24 Hours
     * - 'day': Specific Day report (with 'date' parameter: YYYY-MM-DD)
     * - '7d': Last 7 Days
     * - '30d': Last 30 Days
     */
    public function getHistory(Request $request)
    {
        try {
            $range = $request->query('range', '24h');
            $requestedDate = $request->query('date');

            // Resolve panel timezone for localized display
            $panelTimezone = 'Asia/Kolkata';
            try {
                $dbTz = Setting::where('key', 'timezone')->value('value');
                if (!empty($dbTz)) {
                    $panelTimezone = $dbTz;
                }
            } catch (\Throwable $e) {
                // fallback to Asia/Kolkata
            }

            $nowInPanel = Carbon::now($panelTimezone);
            $startTime = null;
            $endTime = null;
            $reportTitle = 'Resource Usage Report';
            $isSpecificDay = false;
            $selectedDateStr = null;

            if ($range === 'day' || !empty($requestedDate)) {
                $isSpecificDay = true;
                $range = 'day';

                if (!empty($requestedDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate)) {
                    $selectedDateStr = $requestedDate;
                } else {
                    $selectedDateStr = $nowInPanel->format('Y-m-d');
                }

                $dayStart = Carbon::createFromFormat('Y-m-d H:i:s', "{$selectedDateStr} 00:00:00", $panelTimezone)->setTimezone('UTC');
                $dayEnd = Carbon::createFromFormat('Y-m-d H:i:s', "{$selectedDateStr} 23:59:59", $panelTimezone)->setTimezone('UTC');

                $startTime = $dayStart;
                $endTime = $dayEnd;
                $displayDate = Carbon::createFromFormat('Y-m-d', $selectedDateStr, $panelTimezone);
                $reportTitle = "Daily Report: " . $displayDate->format('l, M d, Y');
            } elseif ($range === '1h') {
                $startTime = Carbon::now('UTC')->subHour();
                $endTime = Carbon::now('UTC');
                $reportTitle = "Last 1 Hour Resource Report";
            } elseif ($range === '7d') {
                $startTime = Carbon::now('UTC')->subDays(7);
                $endTime = Carbon::now('UTC');
                $reportTitle = "7-Day Resource Trend Report";
            } elseif ($range === '30d') {
                $startTime = Carbon::now('UTC')->subDays(30);
                $endTime = Carbon::now('UTC');
                $reportTitle = "30-Day Monthly Resource Report";
            } else {
                $range = '24h';
                $startTime = Carbon::now('UTC')->subHours(24);
                $endTime = Carbon::now('UTC');
                $reportTitle = "Last 24 Hours Resource Report";
            }

            // Query metrics for the specified window
            $query = ServerMetric::where('created_at', '>=', $startTime);
            if ($endTime) {
                $query->where('created_at', '<=', $endTime);
            }
            $metrics = $query->orderBy('created_at', 'asc')->get();

            // If empty and range includes right now, generate initial snapshot
            if ($metrics->isEmpty() && $startTime <= Carbon::now('UTC') && (!$endTime || $endTime >= Carbon::now('UTC'))) {
                Artisan::call('nimbus:collect-metrics');
                $metrics = ServerMetric::where('created_at', '>=', $startTime)
                    ->when($endTime, fn($q) => $q->where('created_at', '<=', $endTime))
                    ->orderBy('created_at', 'asc')
                    ->get();
            }

            // Downsample / group points based on range to optimize frontend chart rendering
            $groupMinutes = match ($range) {
                '1h' => 1,
                '7d' => 60,   // 1-hour slots
                '30d' => 240, // 4-hour slots
                default => 5, // 5-minute slots for daily / 24h
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
                        'memory' => (float) $m->memory_percent,
                        'memory_used_mb' => (float) $m->memory_used_mb,
                        'memory_total_mb' => (float) $m->memory_total_mb,
                        'swap_used_mb' => (float) ($m->swap_used_mb ?? 0),
                        'load' => (float) $m->load_1min,
                        'disk' => (float) $m->disk_percent,
                    ];
                }
            } else {
                // Group by bucket
                $buckets = [];
                foreach ($metrics as $m) {
                    $bucketKey = floor($m->created_at->timestamp / ($groupMinutes * 60));
                    $buckets[$bucketKey][] = $m;
                }

                foreach ($buckets as $bMetrics) {
                    $count = count($bMetrics);
                    $cpuAvg = round(array_sum(array_column($bMetrics, 'cpu_percent')) / $count, 1);
                    $memAvg = round(array_sum(array_column($bMetrics, 'memory_percent')) / $count, 1);
                    $loadAvg = round(array_sum(array_column($bMetrics, 'load_1min')) / $count, 2);
                    $diskAvg = round(array_sum(array_column($bMetrics, 'disk_percent')) / $count, 1);
                    $usedMb = round(array_sum(array_column($bMetrics, 'memory_used_mb')) / $count, 0);
                    $totalMb = (float) $bMetrics[0]->memory_total_mb;
                    $swapMb = round(array_sum(array_column($bMetrics, 'swap_used_mb')) / $count, 0);

                    $firstTime = $bMetrics[0]->created_at->copy()->setTimezone($panelTimezone);
                    $points[] = [
                        'time' => $firstTime->format('M d, H:i'),
                        'full_time' => $firstTime->format('M d, Y H:i'),
                        'timestamp' => $bMetrics[0]->created_at->timestamp,
                        'cpu' => $cpuAvg,
                        'memory' => $memAvg,
                        'memory_used_mb' => $usedMb,
                        'memory_total_mb' => $totalMb,
                        'swap_used_mb' => $swapMb,
                        'load' => $loadAvg,
                        'disk' => $diskAvg,
                    ];
                }
            }

            // Extract statistical distributions for AWS-style KPIs
            $cpuValues = $metrics->pluck('cpu_percent')->filter(fn($v) => is_numeric($v))->map(fn($v) => (float) $v)->values()->all();
            $memValues = $metrics->pluck('memory_percent')->filter(fn($v) => is_numeric($v))->map(fn($v) => (float) $v)->values()->all();
            $loadValues = $metrics->pluck('load_1min')->filter(fn($v) => is_numeric($v))->map(fn($v) => (float) $v)->values()->all();

            $hardware = ServerMetricsService::getCpuHardware();
            $cores = $hardware['cores'] ?? 1;

            $avgCpu = !empty($cpuValues) ? round(array_sum($cpuValues) / count($cpuValues), 1) : 0.0;
            $minCpu = !empty($cpuValues) ? min($cpuValues) : 0.0;
            $p95Cpu = ServerMetricsService::calculatePercentile($cpuValues, 95);

            $avgMem = !empty($memValues) ? round(array_sum($memValues) / count($memValues), 1) : 0.0;
            $minMem = !empty($memValues) ? min($memValues) : 0.0;
            $p95Mem = ServerMetricsService::calculatePercentile($memValues, 95);

            $avgLoad = !empty($loadValues) ? round(array_sum($loadValues) / count($loadValues), 2) : 0.0;
            $peakLoadMetric = $metrics->sortByDesc('load_1min')->first();
            $peakLoad = $peakLoadMetric ? (float) $peakLoadMetric->load_1min : 0.0;
            $loadCapacityPercent = $cores > 0 ? round(($peakLoad / $cores) * 100, 1) : 0.0;

            // Peak CPU with offending process
            $peakCpuMetric = $metrics->sortByDesc('cpu_percent')->first();
            $peakCpu = null;
            if ($peakCpuMetric) {
                $topProc = !empty($peakCpuMetric->top_processes[0]) ? $peakCpuMetric->top_processes[0] : null;
                $peakCpuLoc = $peakCpuMetric->created_at->copy()->setTimezone($panelTimezone);
                $peakCpu = [
                    'value' => (float) $peakCpuMetric->cpu_percent,
                    'time' => $peakCpuLoc->format('M d, H:i'),
                    'full_time' => $peakCpuLoc->format('M d, Y H:i:s'),
                    'process' => $topProc ? ($topProc['command'] ?? $topProc['user'] ?? 'system') : 'system',
                    'user' => $topProc['user'] ?? 'system',
                ];
            }

            // Peak RAM with offending process
            $peakMemMetric = $metrics->sortByDesc('memory_percent')->first();
            $peakMem = null;
            if ($peakMemMetric) {
                $topProc = !empty($peakMemMetric->top_processes[0]) ? $peakMemMetric->top_processes[0] : null;
                $peakMemLoc = $peakMemMetric->created_at->copy()->setTimezone($panelTimezone);
                $peakMem = [
                    'value' => (float) $peakMemMetric->memory_percent,
                    'used_mb' => (float) $peakMemMetric->memory_used_mb,
                    'time' => $peakMemLoc->format('M d, H:i'),
                    'full_time' => $peakMemLoc->format('M d, Y H:i:s'),
                    'process' => $topProc ? ($topProc['command'] ?? $topProc['user'] ?? 'system') : 'system',
                    'user' => $topProc['user'] ?? 'system',
                ];
            }

            // High-usage incidents (CPU > 80% or RAM > 85%)
            $incidents = [];
            $incidentRows = $metrics->where('is_alert_level', true)->sortByDesc('created_at')->take(15);
            foreach ($incidentRows as $row) {
                $topProc = !empty($row->top_processes[0]) ? $row->top_processes[0] : null;
                $incidents[] = [
                    'time' => $row->created_at->copy()->setTimezone($panelTimezone)->format('M d, Y H:i:s'),
                    'cpu' => (float) $row->cpu_percent,
                    'memory' => (float) $row->memory_percent,
                    'load' => (float) $row->load_1min,
                    'process' => $topProc ? ($topProc['command'] ?? 'Unknown') : 'System',
                    'user' => $topProc['user'] ?? 'root',
                ];
            }

            // Aggregate top workloads across the period
            $workloads = [];
            foreach ($metrics as $m) {
                if (!empty($m->top_processes) && is_array($m->top_processes)) {
                    foreach (array_slice($m->top_processes, 0, 3) as $proc) {
                        $cmd = $proc['command'] ?? 'unknown';
                        if (!isset($workloads[$cmd])) {
                            $workloads[$cmd] = [
                                'command' => $cmd,
                                'user' => $proc['user'] ?? 'root',
                                'max_cpu' => 0.0,
                                'max_memory' => 0.0,
                                'count' => 0,
                            ];
                        }
                        $workloads[$cmd]['max_cpu'] = max($workloads[$cmd]['max_cpu'], (float) ($proc['cpu'] ?? 0));
                        $workloads[$cmd]['max_memory'] = max($workloads[$cmd]['max_memory'], (float) ($proc['memory'] ?? 0));
                        $workloads[$cmd]['count']++;
                    }
                }
            }
            usort($workloads, fn($a, $b) => $b['max_cpu'] <=> $a['max_cpu']);
            $topWorkloads = array_slice(array_values($workloads), 0, 8);

            // Determine overall AWS health status
            $totalIncidents = $metrics->where('is_alert_level', true)->count();
            $healthStatus = 'HEALTHY';
            if ($totalIncidents > 5 || $peakLoad >= ($cores * 2.0)) {
                $healthStatus = 'CRITICAL';
            } elseif ($totalIncidents > 0 || $peakLoad >= ($cores * 1.2)) {
                $healthStatus = 'WARNING';
            }

            // Generate list of selectable days in the past 30 days
            $availableDays = [];
            for ($i = 0; $i < 30; $i++) {
                $dateObj = $nowInPanel->copy()->subDays($i);
                $dStr = $dateObj->format('Y-m-d');
                $availableDays[] = [
                    'date' => $dStr,
                    'label' => $i === 0 ? "Today ({$dateObj->format('M d')})" : ($i === 1 ? "Yesterday ({$dateObj->format('M d')})" : $dateObj->format('D, M d, Y')),
                    'formatted' => $dateObj->format('M d, Y'),
                ];
            }

            $periodStartFormatted = $startTime->copy()->setTimezone($panelTimezone)->format('M d, Y H:i');
            $periodEndFormatted = ($endTime ?? Carbon::now('UTC'))->copy()->setTimezone($panelTimezone)->format('M d, Y H:i');

            return response()->json([
                'success' => true,
                'range' => $range,
                'selected_date' => $selectedDateStr,
                'is_specific_day' => $isSpecificDay,
                'report_metadata' => [
                    'title' => $reportTitle,
                    'period_start' => $periodStartFormatted,
                    'period_end' => $periodEndFormatted,
                    'timezone' => $panelTimezone,
                    'health_status' => $healthStatus,
                    'cores' => $cores,
                    'cpu_model' => $hardware['model'] ?? 'Unknown',
                    'total_points' => count($points),
                ],
                'summary' => [
                    'avg_cpu' => $avgCpu,
                    'min_cpu' => $minCpu,
                    'p95_cpu' => $p95Cpu,
                    'peak_cpu' => $peakCpu,
                    'avg_memory' => $avgMem,
                    'min_memory' => $minMem,
                    'p95_memory' => $p95Mem,
                    'peak_memory' => $peakMem,
                    'total_memory_mb' => $metrics->first() ? (float) $metrics->first()->memory_total_mb : 0.0,
                    'avg_load' => $avgLoad,
                    'peak_load' => $peakLoad,
                    'load_capacity_percent' => $loadCapacityPercent,
                    'total_incidents' => $totalIncidents,
                ],
                'points' => $points,
                'incidents' => $incidents,
                'top_workloads' => $topWorkloads,
                'available_days' => $availableDays,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get real-time and historical resource usage for all projects
     */
    public function getProjectsUsage(Request $request)
    {
        try {
            $range = $request->query('range', '24h');
            $data = \App\Services\ProjectMetricsService::getAllProjectsSummary($range);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get historical time-series resource usage for a specific project
     */
    public function getSingleProjectHistory(Request $request, string $domain)
    {
        try {
            $range = $request->query('range', '24h');
            $data = \App\Services\ProjectMetricsService::getProjectHistory($domain, $range);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}

