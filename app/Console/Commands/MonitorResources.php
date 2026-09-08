<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotificationService;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class MonitorResources extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:resources {--threshold=90 : CPU/RAM/Disk threshold percentage} {--duration=30 : Minimum sustained minutes before alerting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check CPU, RAM, and Disk space, and send notifications only if usage sustains for >= 30 minutes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $threshold = (int) ($this->option('threshold') ?: Setting::where('key', 'resource_alert_threshold')->value('value') ?: 90);
        $requiredDurationMinutes = (int) ($this->option('duration') ?: Setting::where('key', 'resource_alert_duration_minutes')->value('value') ?: 30);

        $cpu = $this->getCpuUsage();
        $memory = $this->getMemoryUsage();
        $disks = $this->getDiskUsage();

        $alerts = [];

        // Check CPU (> threshold)
        if ($cpu > $threshold) {
            $alerts[] = "<strong>CPU Usage:</strong> {$cpu}% (Threshold: {$threshold}%)";
        }

        // Check Memory (> threshold)
        if ($memory > $threshold) {
            $alerts[] = "<strong>Memory Usage:</strong> {$memory}% (Threshold: {$threshold}%)";
        }

        // Check Disk space (> threshold)
        foreach ($disks as $disk) {
            if ($disk['percentage'] > $threshold) {
                $alerts[] = "<strong>Disk Usage ({$disk['mount']}):</strong> {$disk['percentage']}% of {$disk['total']} (Threshold: {$threshold}%)";
            }
        }

        $startKey = 'resources_high_usage_started_at';
        $alertSentKey = 'resources_alert_sent';

        if (!empty($alerts)) {
            $now = now()->timestamp;
            $startedAt = Cache::get($startKey);

            if (!$startedAt) {
                // First detection of heavy resource usage: start tracking duration
                Cache::put($startKey, $now, now()->addHours(48));
                $startedAt = $now;
            }

            $elapsedMinutes = (int) round(($now - $startedAt) / 60);

            $this->warn("High resource usage detected ({$elapsedMinutes}m continuous). Threshold requires sustained heavy usage for >= {$requiredDurationMinutes} minutes before alerting.");

            // Only send alert if sustained for >= requiredDurationMinutes (default 30 mins)
            if ($elapsedMinutes >= $requiredDurationMinutes) {
                if (!Cache::has($alertSentKey)) {
                    $topProcesses = $this->getTopProcessesSummary();

                    $content = "<div style='font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color: #333; line-height: 1.6;'>";
                    $content .= "<h3 style='color: #e53e3e; margin-top: 0;'>⚠️ Critical: Sustained High Resource Usage Alert</h3>";
                    $content .= "<p>The Nimbus server resource monitor has detected <strong>sustained heavy resource usage</strong> continuously for <strong>{$elapsedMinutes} minutes</strong> (exceeding the {$requiredDurationMinutes}-minute threshold):</p>";
                    $content .= "<div style='background: #fff5f5; border-left: 4px solid #e53e3e; padding: 12px 16px; margin: 15px 0; border-radius: 4px;'>";
                    $content .= "<ul style='margin: 0; padding-left: 20px;'>";
                    foreach ($alerts as $alert) {
                        $content .= "<li style='margin-bottom: 5px;'>{$alert}</li>";
                    }
                    $content .= "</ul></div>";

                    $content .= "<p style='font-size: 13px; color: #666;'>";
                    $content .= "<strong>Started:</strong> " . Carbon::createFromTimestamp($startedAt)->toDateTimeString() . " (" . $elapsedMinutes . " mins ago)<br>";
                    $content .= "<strong>Current Check:</strong> " . now()->toDateTimeString() . "<br>";
                    $content .= "<strong>Server Hostname:</strong> " . gethostname() . "</p>";

                    if (!empty($topProcesses)) {
                        $content .= "<p style='margin-top: 15px; font-weight: bold;'>Top Active Processes:</p>";
                        $content .= "<pre style='background: #f7fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 6px; font-size: 12px; font-family: monospace; overflow-x: auto; color: #2d3748;'>" . e($topProcesses) . "</pre>";
                    }

                    $content .= "<p style='margin-top: 20px; font-size: 12px; color: #718096;'>This alert is sent only when heavy usage persists continuously for 30 minutes or more to prevent false alarms. Future alerts for this ongoing incident are throttled for 4 hours.</p>";
                    $content .= "</div>";

                    NotificationService::send("CRITICAL: Sustained High Resource Usage Alert ({$elapsedMinutes}m+)", $content);
                    Cache::put($alertSentKey, $now, now()->addHours(4));
                    $this->warn("High resource usage persisted for {$elapsedMinutes} mins (>= {$requiredDurationMinutes} mins)! Alert email sent.");
                } else {
                    $this->info("High resource usage sustained for {$elapsedMinutes} mins, but alert is throttled (already sent recently).");
                }
            } else {
                $this->info("High resource usage has only lasted {$elapsedMinutes}m (threshold is {$requiredDurationMinutes}m). No email sent to avoid spam.");
            }
        } else {
            // Resource usage is back within normal limits
            if (Cache::has($startKey)) {
                $startedAt = Cache::get($startKey);
                $elapsedMinutes = (int) round((now()->timestamp - $startedAt) / 60);

                // If an alert was previously sent for this incident, notify that it has resolved
                if (Cache::has($alertSentKey)) {
                    $recoveryContent = "<div style='font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color: #333; line-height: 1.6;'>";
                    $recoveryContent .= "<h3 style='color: #38a169; margin-top: 0;'>✅ Resolved: Server Resource Usage Returned to Normal</h3>";
                    $recoveryContent .= "<p>Server resource usage has normalized after a period of elevated activity.</p>";
                    $recoveryContent .= "<div style='background: #f0fff4; border-left: 4px solid #38a169; padding: 12px 16px; margin: 15px 0; border-radius: 4px;'>";
                    $recoveryContent .= "<p style='margin: 0;'><strong>Current CPU:</strong> {$cpu}% | <strong>Memory:</strong> {$memory}%</p>";
                    $recoveryContent .= "</div>";
                    $recoveryContent .= "<p style='font-size: 13px; color: #666;'><strong>Resolved At:</strong> " . now()->toDateTimeString() . "</p>";
                    $recoveryContent .= "</div>";

                    NotificationService::send("RESOLVED: Server Resource Usage Normalized", $recoveryContent);
                    $this->info("Sent resource normalized recovery notification.");
                }

                Cache::forget($startKey);
                Cache::forget($alertSentKey);
                $this->info("Resource usage normalized. High usage timer reset.");
            }

            $this->info("Resource usage is within normal limits. CPU: {$cpu}%, Memory: {$memory}%");
        }
    }

    private function getTopProcessesSummary(): string
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return '';
        }

        $output = [];
        @exec("ps -eo pid,user,%cpu,%mem,cmd --sort=-%cpu | head -n 8 2>/dev/null", $output);
        return implode("\n", $output);
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
