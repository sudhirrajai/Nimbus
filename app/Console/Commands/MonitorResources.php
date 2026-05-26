<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Cache;

class MonitorResources extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:resources';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check CPU, RAM, and Disk space, and send notifications if usage exceeds threshold';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $cpu = $this->getCpuUsage();
        $memory = $this->getMemoryUsage();
        $disks = $this->getDiskUsage();

        $alerts = [];

        // Check CPU (> 90%)
        if ($cpu > 90) {
            $alerts[] = "<strong>CPU Usage:</strong> {$cpu}% (Threshold: 90%)";
        }

        // Check Memory (> 90%)
        if ($memory > 90) {
            $alerts[] = "<strong>Memory Usage:</strong> {$memory}% (Threshold: 90%)";
        }

        // Check Disk space (> 90%)
        foreach ($disks as $disk) {
            if ($disk['percentage'] > 90) {
                $alerts[] = "<strong>Disk Usage ({$disk['mount']}):</strong> {$disk['percentage']}% of {$disk['total']} (Threshold: 90%)";
            }
        }

        if (!empty($alerts)) {
            // To prevent spamming the administrator, rate-limit alerts of the same type to once every 4 hours
            $cacheKey = 'resources_alert_sent';
            if (!Cache::has($cacheKey)) {
                $content = "<p>Hello,</p><p>The Nimbus system resource monitor has detected high resource usage on your server:</p><ul>";
                foreach ($alerts as $alert) {
                    $content .= "<li>{$alert}</li>";
                }
                $content .= "</ul><p><strong>Time:</strong> " . now()->toDateTimeString() . "</p><p>Please inspect the system processes to resolve this issue.</p>";

                NotificationService::send("CRITICAL: High Resource Usage Alert", $content);
                Cache::put($cacheKey, true, now()->addHours(4));
                $this->warn("High resource usage detected! Alert sent.");
            } else {
                $this->info("High resource usage detected, but alert is throttled.");
            }
        } else {
            $this->info("Resource usage is within normal limits. CPU: {$cpu}%, Memory: {$memory}%");
        }
    }

    private function getCpuUsage()
    {
        if (!file_exists('/proc/stat')) {
            return 0;
        }

        $stat1 = @file_get_contents('/proc/stat');
        usleep(100000); // 100ms
        $stat2 = @file_get_contents('/proc/stat');

        if (!$stat1 || !$stat2) {
            return 0;
        }

        $info1 = $this->parseCpuStat($stat1);
        $info2 = $this->parseCpuStat($stat2);

        $total = $info2['total'] - $info1['total'];
        $idle = $info2['idle'] - $info1['idle'];

        if ($total == 0) {
            return 0;
        }

        return round(100 * (1 - $idle / $total), 1);
    }

    private function parseCpuStat($stat)
    {
        $lines = explode("\n", $stat);
        $cpu = explode(" ", preg_replace("/cpu\s+/", "", $lines[0]));

        return [
            'idle' => $cpu[3],
            'total' => array_sum($cpu)
        ];
    }

    private function getMemoryUsage()
    {
        if (!file_exists('/proc/meminfo')) {
            return 0;
        }

        $meminfo = @file_get_contents('/proc/meminfo');
        if (!$meminfo) {
            return 0;
        }

        $lines = explode("\n", $meminfo);
        $mem = [];

        foreach ($lines as $line) {
            if (preg_match('/^(\w+):\s+(\d+)\s+kB/', $line, $matches)) {
                $mem[$matches[1]] = (int)$matches[2] * 1024;
            }
        }

        $total = $mem['MemTotal'] ?? 0;
        $available = $mem['MemAvailable'] ?? ($mem['MemFree'] ?? 0);

        if ($total == 0) {
            return 0;
        }

        $used = $total - $available;
        return round(($used / $total) * 100, 1);
    }

    private function getDiskUsage()
    {
        $disks = [];
        $output = [];
        
        @exec("df -B1 / /var/www 2>/dev/null | tail -n +2", $output);

        foreach ($output as $line) {
            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 6) {
                $disks[] = [
                    'filesystem' => $parts[0],
                    'total' => $this->formatBytes((int)$parts[1]),
                    'percentage' => (int)str_replace('%', '', $parts[4]),
                    'mount' => $parts[5]
                ];
            }
        }

        return $disks;
    }

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
