<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ServerMetric;
use Illuminate\Support\Facades\Log;

class CollectServerMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nimbus:collect-metrics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Accurately collect server resource usage and store in 30-day metrics history';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $cpuInfo = \App\Services\ServerMetricsService::getCpuUsage();
            $cpuPercent = (float) $cpuInfo['usage'];
            $memData = \App\Services\ServerMetricsService::getMemoryUsage();
            $loadData = \App\Services\ServerMetricsService::getLoadAverage();
            $disks = \App\Services\ServerMetricsService::getDiskUsage();
            $diskPercent = (float) ($disks[0]['percentage'] ?? 0);
            $topProcesses = \App\Services\ServerMetricsService::getTopProcesses(5);

            $isAlert = ($cpuPercent >= 80.0 || $memData['percentage'] >= 85.0 || $loadData['1min'] >= 2.5);

            $metric = ServerMetric::create([
                'cpu_percent' => $cpuPercent,
                'memory_used_mb' => $memData['used_mb'],
                'memory_total_mb' => $memData['total_mb'],
                'memory_percent' => $memData['percentage'],
                'swap_used_mb' => $memData['swap_used_mb'],
                'disk_percent' => $diskPercent,
                'load_1min' => $loadData['1min'],
                'load_5min' => $loadData['5min'],
                'load_15min' => $loadData['15min'],
                'top_processes' => $topProcesses,
                'is_alert_level' => $isAlert,
                'created_at' => now(),
            ]);

            // Collect per-project resource snapshot
            try {
                \App\Services\ProjectMetricsService::collectSnapshot();
            } catch (\Throwable $pe) {
                Log::warning("Failed to collect per-project metrics: " . $pe->getMessage());
            }

            // Rolling 30-day retention prune
            ServerMetric::where('created_at', '<', now()->subDays(30))->delete();

            $this->info("Metrics collected: CPU {$cpuPercent}%, RAM {$memData['percent']}%, Load {$loadData['1min']}");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            Log::error("Failed to collect server metrics: " . $e->getMessage());
            $this->error("Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Measure CPU usage accurately over a 1-second sample interval.
     */
    private function getCpuUsage(): float
    {
        if (!file_exists('/proc/stat')) {
            return 0.0;
        }

        $stat1 = @file_get_contents('/proc/stat');
        sleep(1);
        $stat2 = @file_get_contents('/proc/stat');

        if (!$stat1 || !$stat2) {
            return 0.0;
        }

        $c1 = $this->parseStatLine($stat1);
        $c2 = $this->parseStatLine($stat2);

        $totalDiff = $c2['total'] - $c1['total'];
        $idleDiff = $c2['idle'] - $c1['idle'];

        if ($totalDiff <= 0) {
            return 0.0;
        }

        $usage = (1 - ($idleDiff / $totalDiff)) * 100;
        return max(0.0, min(100.0, round($usage, 2)));
    }

    private function parseStatLine(string $stat): array
    {
        $lines = explode("\n", $stat);
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
     * Accurate memory usage from /proc/meminfo.
     */
    private function getMemoryData(): array
    {
        if (!file_exists('/proc/meminfo')) {
            return ['used_mb' => 0, 'total_mb' => 0, 'percent' => 0, 'swap_used_mb' => 0];
        }

        $meminfo = @file_get_contents('/proc/meminfo');
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

        $swapTotal = $mem['SwapTotal'] ?? 0;
        $swapFree = $mem['SwapFree'] ?? 0;
        $swapUsed = max(0, $swapTotal - $swapFree);

        $percent = $total > 0 ? round(($used / $total) * 100, 2) : 0.0;

        return [
            'used_mb' => round($used / 1048576, 2),
            'total_mb' => round($total / 1048576, 2),
            'percent' => $percent,
            'swap_used_mb' => round($swapUsed / 1048576, 2),
        ];
    }

    /**
     * System load averages (1m, 5m, 15m).
     */
    private function getLoadAverage(): array
    {
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : [0.0, 0.0, 0.0];
        return [
            '1min' => round($load[0] ?? 0.0, 2),
            '5min' => round($load[1] ?? 0.0, 2),
            '15min' => round($load[2] ?? 0.0, 2),
        ];
    }

    /**
     * Root disk usage percentage.
     */
    private function getDiskPercentage(): float
    {
        $output = [];
        @exec("df -P / 2>/dev/null | tail -n 1", $output);
        if (!empty($output[0])) {
            $parts = preg_split('/\s+/', $output[0]);
            if (isset($parts[4])) {
                return (float) str_replace('%', '', $parts[4]);
            }
        }
        return 0.0;
    }

    /**
     * Top active processes sorted by CPU usage.
     */
    private function getTopProcesses(): array
    {
        $processes = [];
        $output = [];
        @exec("ps -eo pid,user,%cpu,%mem,cmd --sort=-%cpu | head -n 6 | tail -n 5 2>/dev/null", $output);

        foreach ($output as $line) {
            $parts = preg_split('/\s+/', trim($line), 5);
            if (count($parts) >= 5) {
                $processes[] = [
                    'pid' => (int) $parts[0],
                    'user' => $parts[1],
                    'cpu' => (float) $parts[2],
                    'memory' => (float) $parts[3],
                    'command' => substr($parts[4], 0, 70),
                ];
            }
        }

        return $processes;
    }
}
