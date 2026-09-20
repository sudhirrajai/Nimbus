<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\UserWebsite;
use App\Models\ProjectMetric;
use App\Models\GitDeployment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;

class ProjectControlService
{
    private const SUSPENDED_DIR = '/etc/nimbus/suspended';
    private const SETTING_KEY = 'suspended_projects';

    /**
     * Check if a domain is currently suspended
     */
    public static function isSuspended(string $domain): bool
    {
        $domain = trim(strtolower($domain));
        if (empty($domain)) return false;

        // Check cache / Setting first
        $suspendedList = self::getSuspendedDomains();
        if (in_array($domain, $suspendedList, true)) {
            return true;
        }

        // Fallback: check marker file in /etc/nimbus/suspended
        $markerPath = self::SUSPENDED_DIR . '/' . $domain . '.json';
        if (file_exists($markerPath)) {
            return true;
        }

        // Fallback: check in-site marker
        if (file_exists("/var/www/{$domain}/.nimbus_suspended")) {
            return true;
        }

        return false;
    }

    /**
     * Get list of all suspended domain names
     */
    public static function getSuspendedDomains(): array
    {
        return Cache::remember('nimbus_suspended_projects_list', 10, function () {
            try {
                $record = Setting::where('key', self::SETTING_KEY)->first();
                if ($record && !empty($record->value)) {
                    $decoded = json_decode($record->value, true);
                    if (is_array($decoded)) {
                        return array_values(array_unique(array_map('strtolower', $decoded)));
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Failed to fetch suspended projects setting: " . $e->getMessage());
            }

            // Fallback to directory scan
            $domains = [];
            if (is_dir(self::SUSPENDED_DIR)) {
                $files = @glob(self::SUSPENDED_DIR . '/*.json') ?: [];
                foreach ($files as $f) {
                    $d = basename($f, '.json');
                    if (!empty($d)) {
                        $domains[] = strtolower($d);
                    }
                }
            }
            return array_values(array_unique($domains));
        });
    }

    /**
     * Suspend a project / domain (temporarily turn OFF all resources)
     * - Stops & pauses Supervisor queue workers
     * - Pauses Cron jobs across all users
     * - Kills active running processes (releases RAM & CPU immediately)
     * - Disables Nginx web access
     * - Persists suspension state
     */
    public static function suspend(string $domain, string $reason = 'Manual suspension by administrator'): array
    {
        $domain = trim($domain);
        $domainLower = strtolower($domain);
        $results = [
            'domain' => $domain,
            'status' => 'suspended',
            'workers_stopped' => [],
            'crons_paused' => 0,
            'processes_killed' => 0,
            'nginx_disabled' => false,
            'success' => true,
        ];

        try {
            Log::info("Suspending project: {$domain} (Reason: {$reason})");

            // 1. Pause Supervisor workers
            $results['workers_stopped'] = self::stopSupervisorWorkers($domain, false);

            // 2. Pause Cron jobs
            $results['crons_paused'] = self::disableCrons($domain);

            // 3. Kill running processes
            $results['processes_killed'] = self::killDomainProcesses($domain);

            // 4. Disable Nginx vhost symlink
            $results['nginx_disabled'] = self::disableNginx($domain);

            // 5. Create in-site marker
            if (is_dir("/var/www/{$domain}")) {
                @file_put_contents("/var/www/{$domain}/.nimbus_suspended", json_encode([
                    'suspended_at' => now()->toIso8601String(),
                    'reason' => $reason,
                ]));
            }

            // 6. Persist system marker in /etc/nimbus/suspended/{domain}.json
            self::executeSudo("mkdir -p " . escapeshellarg(self::SUSPENDED_DIR));
            $markerData = json_encode([
                'domain' => $domain,
                'suspended_at' => now()->toIso8601String(),
                'reason' => $reason,
                'workers' => $results['workers_stopped'],
                'crons_paused' => $results['crons_paused'],
            ], JSON_PRETTY_PRINT);

            $tempPath = tempnam('/tmp', 'nimbus_susp_');
            file_put_contents($tempPath, $markerData);
            self::executeSudo("mv " . escapeshellarg($tempPath) . " " . escapeshellarg(self::SUSPENDED_DIR . "/{$domainLower}.json"));
            self::executeSudo("chmod 644 " . escapeshellarg(self::SUSPENDED_DIR . "/{$domainLower}.json"));

            // 7. Update database setting and clear cache
            self::addSuspendedSetting($domainLower);

            Log::info("Successfully suspended project {$domain}: " . json_encode($results));
        } catch (\Throwable $e) {
            $results['success'] = false;
            $results['error'] = $e->getMessage();
            Log::error("Error suspending project {$domain}: " . $e->getMessage());
        }

        return $results;
    }

    /**
     * Resume a project / domain (turn back ON all resources)
     * - Restores Nginx web access
     * - Restores and restarts Supervisor queue workers
     * - Restores scheduled Cron jobs
     * - Clears suspension state
     */
    public static function resume(string $domain): array
    {
        $domain = trim($domain);
        $domainLower = strtolower($domain);
        $results = [
            'domain' => $domain,
            'status' => 'active',
            'workers_resumed' => [],
            'crons_resumed' => 0,
            'nginx_enabled' => false,
            'success' => true,
        ];

        try {
            Log::info("Resuming project: {$domain}");

            // 1. Enable Nginx vhost symlink
            $results['nginx_enabled'] = self::enableNginx($domain);

            // 2. Resume & restart Supervisor workers
            $results['workers_resumed'] = self::resumeSupervisorWorkers($domain);

            // 3. Resume Cron jobs
            $results['crons_resumed'] = self::resumeCrons($domain);

            // 4. Remove markers
            if (file_exists("/var/www/{$domain}/.nimbus_suspended")) {
                @unlink("/var/www/{$domain}/.nimbus_suspended");
            }
            self::executeSudo("rm -f " . escapeshellarg(self::SUSPENDED_DIR . "/{$domainLower}.json"));

            // 5. Update database setting and clear cache
            self::removeSuspendedSetting($domainLower);

            // 6. Reload PHP-FPM pool if applicable
            $slug = SiteIsolationService::slug($domain);
            self::executeSudo("systemctl reload php8.3-fpm 2>/dev/null || true");
            self::executeSudo("systemctl reload php8.2-fpm 2>/dev/null || true");

            Log::info("Successfully resumed project {$domain}: " . json_encode($results));
        } catch (\Throwable $e) {
            $results['success'] = false;
            $results['error'] = $e->getMessage();
            Log::error("Error resuming project {$domain}: " . $e->getMessage());
        }

        return $results;
    }

    /**
     * Toggle domain suspension state
     */
    public static function toggle(string $domain): array
    {
        if (self::isSuspended($domain)) {
            return self::resume($domain);
        }
        return self::suspend($domain);
    }

    /**
     * Permanently delete a domain and cleanly wipe all its associated files, crons, supervisors, etc.
     */
    public static function cleanupDomainCompletely(string $domain): array
    {
        $domain = trim($domain);
        $domainLower = strtolower($domain);
        $results = [
            'domain' => $domain,
            'workers_deleted' => [],
            'crons_deleted' => 0,
            'processes_killed' => 0,
            'nginx_deleted' => false,
            'ssl_deleted' => false,
            'files_deleted' => false,
            'db_cleaned' => false,
            'success' => true,
        ];

        try {
            Log::info("Starting comprehensive deletion cleanup for domain: {$domain}");

            // 1. Stop & permanently delete Supervisor workers
            $results['workers_deleted'] = self::stopSupervisorWorkers($domain, true);

            // 2. Permanently delete all cron jobs
            $results['crons_deleted'] = self::deleteCrons($domain);

            // 3. Kill running processes
            $results['processes_killed'] = self::killDomainProcesses($domain);

            // 4. Delete Nginx config & symlinks
            $results['nginx_deleted'] = self::deleteNginx($domain);

            // 5. Delete SSL Certificates via Certbot
            $results['ssl_deleted'] = self::deleteSslCerts($domain);

            // 6. Delete isolated PHP-FPM pool
            SiteIsolationService::deletePool($domain);

            // 7. Remove directory /var/www/{domain}
            $targetPath = "/var/www/{$domain}";
            if (File::exists($targetPath)) {
                $safePath = escapeshellarg($targetPath);
                self::executeSudo("rm -rf {$safePath}");
                $results['files_deleted'] = !File::exists($targetPath);
            } else {
                $results['files_deleted'] = true;
            }

            // 8. Delete associated database records
            UserWebsite::where('domain', $domain)->delete();
            ProjectMetric::where('domain', $domain)->delete();
            try {
                GitDeployment::where('domain', $domain)->delete();
            } catch (\Throwable $e) {}

            // Clean up cron runs logs for this domain
            $cronRunFiles = @glob(storage_path('app/cron_runs/*.json')) ?: [];
            foreach ($cronRunFiles as $crFile) {
                $content = @file_get_contents($crFile);
                if ($content && str_contains($content, "/var/www/{$domain}")) {
                    @unlink($crFile);
                }
            }

            // 9. Remove suspension markers if any
            self::executeSudo("rm -f " . escapeshellarg(self::SUSPENDED_DIR . "/{$domainLower}.json"));
            self::removeSuspendedSetting($domainLower);
            $results['db_cleaned'] = true;

            Log::info("Comprehensive deletion cleanup finished for {$domain}: " . json_encode($results));
        } catch (\Throwable $e) {
            $results['success'] = false;
            $results['error'] = $e->getMessage();
            Log::error("Failed complete cleanup for domain {$domain}: " . $e->getMessage());
        }

        return $results;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SUPERVISOR MANAGEMENT
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Find and stop supervisor workers. If $delete is true, remove conf files completely.
     * Otherwise, rename to .conf.nimbus_suspended.
     */
    private static function stopSupervisorWorkers(string $domain, bool $delete = false): array
    {
        $stopped = [];
        $confDir = '/etc/supervisor/conf.d';
        if (!is_dir($confDir)) return $stopped;

        $slug = SiteIsolationService::slug($domain);
        $files = @glob("{$confDir}/*.conf*") ?: [];

        foreach ($files as $file) {
            $filename = basename($file);
            // Skip nimbus internal workers
            if (str_starts_with($filename, 'nimbus-')) continue;

            $content = @file_get_contents($file);
            if (!$content) {
                $out = [];
                exec("sudo cat " . escapeshellarg($file) . " 2>/dev/null", $out);
                $content = implode("\n", $out);
            }

            $matchesDomain = false;
            // Match 1: explicit domain name or path in config
            if (str_contains($content, "/var/www/{$domain}")) {
                $matchesDomain = true;
            }
            // Match 2: filename starts with or matches domain or slug
            elseif (
                str_starts_with($filename, "{$domain}-") || 
                str_starts_with($filename, "{$slug}-") ||
                str_starts_with($filename, "laravel-{$domain}") ||
                str_starts_with($filename, "laravel-{$slug}") ||
                str_contains($filename, $slug)
            ) {
                $matchesDomain = true;
            }

            if ($matchesDomain) {
                // Extract program name from [program:name]
                $programName = null;
                if (preg_match('/\[program:([^\]]+)\]/', $content, $m)) {
                    $programName = trim($m[1]);
                } else {
                    $programName = preg_replace('/\.conf(\.nimbus_suspended|\.disabled)?$/', '', $filename);
                }

                // Stop the supervisor program
                if ($programName) {
                    self::executeSudo("supervisorctl stop " . escapeshellarg("{$programName}:*") . " 2>/dev/null || supervisorctl stop " . escapeshellarg($programName) . " 2>/dev/null");
                    $stopped[] = $programName;
                }

                if ($delete) {
                    self::executeSudo("rm -f " . escapeshellarg($file));
                } else {
                    // Rename to .conf.nimbus_suspended if currently .conf
                    if (str_ends_with($file, '.conf')) {
                        $newPath = "{$file}.nimbus_suspended";
                        self::executeSudo("mv " . escapeshellarg($file) . " " . escapeshellarg($newPath));
                    }
                }
            }
        }

        if (!empty($stopped)) {
            self::executeSudo("supervisorctl reread 2>&1");
            self::executeSudo("supervisorctl update 2>&1");
        }

        return $stopped;
    }

    /**
     * Restore and restart suspended supervisor workers
     */
    private static function resumeSupervisorWorkers(string $domain): array
    {
        $resumed = [];
        $confDir = '/etc/supervisor/conf.d';
        if (!is_dir($confDir)) return $resumed;

        $slug = SiteIsolationService::slug($domain);
        $files = @glob("{$confDir}/*.nimbus_suspended") ?: [];

        foreach ($files as $file) {
            $filename = basename($file);
            $content = @file_get_contents($file);
            if (!$content) {
                $out = [];
                exec("sudo cat " . escapeshellarg($file) . " 2>/dev/null", $out);
                $content = implode("\n", $out);
            }

            $matchesDomain = false;
            if (str_contains($content, "/var/www/{$domain}")) {
                $matchesDomain = true;
            } elseif (
                str_contains($filename, $domain) || 
                str_contains($filename, $slug)
            ) {
                $matchesDomain = true;
            }

            if ($matchesDomain) {
                $newPath = preg_replace('/\.nimbus_suspended$/', '', $file);
                self::executeSudo("mv " . escapeshellarg($file) . " " . escapeshellarg($newPath));

                $programName = null;
                if (preg_match('/\[program:([^\]]+)\]/', $content, $m)) {
                    $programName = trim($m[1]);
                } else {
                    $programName = basename($newPath, '.conf');
                }
                if ($programName) {
                    $resumed[] = $programName;
                }
            }
        }

        if (!empty($resumed)) {
            self::executeSudo("supervisorctl reread 2>&1");
            self::executeSudo("supervisorctl update 2>&1");
            foreach ($resumed as $prog) {
                self::executeSudo("supervisorctl start " . escapeshellarg("{$prog}:*") . " 2>/dev/null || supervisorctl start " . escapeshellarg($prog) . " 2>/dev/null");
            }
        }

        return $resumed;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CRON MANAGEMENT
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Disable/pause all crons referencing /var/www/{domain}
     */
    private static function disableCrons(string $domain): int
    {
        $count = 0;
        $users = self::getRelevantUsers($domain);

        foreach ($users as $user) {
            $existing = [];
            exec("sudo crontab -u " . escapeshellarg($user) . " -l 2>/dev/null", $existing, $code);
            if ($code !== 0 || empty($existing)) continue;

            $updatedLines = [];
            $modified = false;

            foreach ($existing as $line) {
                $trimmed = trim($line);
                if (empty($trimmed)) {
                    $updatedLines[] = $line;
                    continue;
                }

                // If line targets /var/www/{domain} and is not already suspended
                if (str_contains($trimmed, "/var/www/{$domain}")) {
                    if (!str_starts_with($trimmed, "# [NIMBUS_SUSPENDED:{$domain}]")) {
                        // Strip leading # if any other comment, then add suspend tag
                        $cleanLine = ltrim($trimmed, '# ');
                        $updatedLines[] = "# [NIMBUS_SUSPENDED:{$domain}] {$cleanLine}";
                        $count++;
                        $modified = true;
                        continue;
                    }
                }
                $updatedLines[] = $line;
            }

            if ($modified) {
                self::writeCrontab($user, $updatedLines);
            }
        }

        return $count;
    }

    /**
     * Resume all paused crons referencing /var/www/{domain}
     */
    private static function resumeCrons(string $domain): int
    {
        $count = 0;
        $users = self::getRelevantUsers($domain);

        foreach ($users as $user) {
            $existing = [];
            exec("sudo crontab -u " . escapeshellarg($user) . " -l 2>/dev/null", $existing, $code);
            if ($code !== 0 || empty($existing)) continue;

            $updatedLines = [];
            $modified = false;

            foreach ($existing as $line) {
                $trimmed = trim($line);
                $tag = "# [NIMBUS_SUSPENDED:{$domain}]";

                if (str_starts_with($trimmed, $tag)) {
                    $restored = trim(substr($trimmed, strlen($tag)));
                    $updatedLines[] = $restored;
                    $count++;
                    $modified = true;
                } else {
                    $updatedLines[] = $line;
                }
            }

            if ($modified) {
                self::writeCrontab($user, $updatedLines);
            }
        }

        return $count;
    }

    /**
     * Permanently delete all crons referencing /var/www/{domain}
     */
    private static function deleteCrons(string $domain): int
    {
        $count = 0;
        $users = self::getRelevantUsers($domain);

        foreach ($users as $user) {
            $existing = [];
            exec("sudo crontab -u " . escapeshellarg($user) . " -l 2>/dev/null", $existing, $code);
            if ($code !== 0 || empty($existing)) continue;

            $updatedLines = [];
            $modified = false;

            foreach ($existing as $line) {
                if (str_contains($line, "/var/www/{$domain}")) {
                    $count++;
                    $modified = true;
                    continue; // Drop line
                }
                $updatedLines[] = $line;
            }

            if ($modified) {
                self::writeCrontab($user, $updatedLines);
            }
        }

        // Also if dedicated site user has a crontab, remove it completely
        $siteUser = SiteIsolationService::siteUser($domain);
        self::executeSudo("crontab -u " . escapeshellarg($siteUser) . " -r 2>/dev/null || true");

        return $count;
    }

    private static function getRelevantUsers(string $domain): array
    {
        $siteUser = SiteIsolationService::siteUser($domain);
        return array_unique(['www-data', 'root', $siteUser]);
    }

    private static function writeCrontab(string $user, array $lines): void
    {
        $content = implode("\n", $lines) . "\n";
        $temp = tempnam('/tmp', 'crontab_');
        file_put_contents($temp, $content);
        self::executeSudo("crontab -u " . escapeshellarg($user) . " " . escapeshellarg($temp));
        @unlink($temp);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PROCESS MANAGEMENT
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Terminate all running processes associated with a domain
     */
    private static function killDomainProcesses(string $domain): int
    {
        $siteUser = SiteIsolationService::siteUser($domain);
        $killedCount = 0;

        // 1. Terminate all processes running under the dedicated user
        $pids = [];
        exec("sudo pgrep -u " . escapeshellarg($siteUser) . " 2>/dev/null", $pids);

        // 2. Terminate any worker/php process pointing to /var/www/{domain}
        $procLines = [];
        exec("ps -eo pid,args --no-headers 2>/dev/null", $procLines);
        foreach ($procLines as $pl) {
            $parts = preg_split('/\s+/', trim($pl), 2);
            if (count($parts) >= 2) {
                $pid = (int) $parts[0];
                $cmd = $parts[1];
                // Never kill Nimbus panel
                if (str_contains($cmd, '/usr/local/nimbus')) continue;

                if (str_contains($cmd, "/var/www/{$domain}")) {
                    $pids[] = $pid;
                }
            }
        }

        $uniquePids = array_unique(array_filter(array_map('intval', $pids)));
        if (!empty($uniquePids)) {
            foreach ($uniquePids as $pid) {
                self::executeSudo("kill -15 {$pid} 2>/dev/null || true");
                $killedCount++;
            }
            usleep(300000); // 300ms wait
            foreach ($uniquePids as $pid) {
                self::executeSudo("kill -9 {$pid} 2>/dev/null || true");
            }
        }

        return $killedCount;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // NGINX MANAGEMENT
    // ─────────────────────────────────────────────────────────────────────────

    private static function disableNginx(string $domain): bool
    {
        $enabledPath = "/etc/nginx/sites-enabled/{$domain}";
        if (file_exists($enabledPath) || is_link($enabledPath)) {
            self::executeSudo("rm -f " . escapeshellarg($enabledPath));
            self::executeSudo("nginx -t && systemctl reload nginx");
            return true;
        }
        return false;
    }

    private static function enableNginx(string $domain): bool
    {
        $availablePath = "/etc/nginx/sites-available/{$domain}";
        $enabledPath = "/etc/nginx/sites-enabled/{$domain}";

        if (file_exists($availablePath) && (!file_exists($enabledPath) && !is_link($enabledPath))) {
            self::executeSudo("ln -s " . escapeshellarg($availablePath) . " " . escapeshellarg($enabledPath));
            self::executeSudo("nginx -t && systemctl reload nginx");
            return true;
        }
        return false;
    }

    private static function deleteNginx(string $domain): bool
    {
        $availablePath = "/etc/nginx/sites-available/{$domain}";
        $enabledPath = "/etc/nginx/sites-enabled/{$domain}";

        self::executeSudo("rm -f " . escapeshellarg($enabledPath));
        self::executeSudo("rm -f " . escapeshellarg($availablePath));
        self::executeSudo("rm -f " . escapeshellarg("{$availablePath}.*"));

        self::executeSudo("nginx -t && systemctl reload nginx");
        return true;
    }

    private static function deleteSslCerts(string $domain): bool
    {
        // Certbot delete
        self::executeSudo("certbot delete --cert-name " . escapeshellarg($domain) . " --non-interactive 2>&1");
        return true;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SETTING HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private static function addSuspendedSetting(string $domainLower): void
    {
        try {
            $list = self::getSuspendedDomains();
            if (!in_array($domainLower, $list, true)) {
                $list[] = $domainLower;
                Setting::updateOrCreate(
                    ['key' => self::SETTING_KEY],
                    ['value' => json_encode(array_values(array_unique($list)))]
                );
            }
            Cache::forget('nimbus_suspended_projects_list');
        } catch (\Throwable $e) {
            Log::warning("Failed to add suspended domain to Setting: " . $e->getMessage());
        }
    }

    private static function removeSuspendedSetting(string $domainLower): void
    {
        try {
            $list = self::getSuspendedDomains();
            $list = array_values(array_diff($list, [$domainLower]));
            Setting::updateOrCreate(
                ['key' => self::SETTING_KEY],
                ['value' => json_encode($list)]
            );
            Cache::forget('nimbus_suspended_projects_list');
        } catch (\Throwable $e) {
            Log::warning("Failed to remove suspended domain from Setting: " . $e->getMessage());
        }
    }

    private static function executeSudo(string $command): array
    {
        exec("sudo {$command} 2>&1", $output, $code);
        return ['code' => $code, 'output' => $output];
    }
}
