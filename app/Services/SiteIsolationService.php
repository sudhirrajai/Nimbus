<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class SiteIsolationService
{
    /**
     * Generate a short, safe slug for a domain.
     */
    public static function slug(string $domain): string
    {
        $slug = str_replace(
            ['.ownsoftwaresolutions.com', '.sudhirrajai.com', '.vmcore.in', '.socialspecta.com', '.com', '.in'],
            ['_own', '_sr', '_vm', '_ss', '', ''],
            $domain
        );
        $slug = preg_replace('/[^a-zA-Z0-9_]/', '_', $slug);
        $slug = strtolower(trim($slug, '_'));
        if (strlen($slug) > 18) {
            $slug = substr($slug, 0, 18);
        }
        return $slug ?: 'site';
    }

    /**
     * Dedicated Linux user for domain.
     */
    public static function siteUser(string $domain): string
    {
        return 'site_' . self::slug($domain);
    }

    /**
     * Isolated Unix socket path for domain PHP pool.
     */
    public static function socketPath(string $domain, string $phpVersion = '8.3'): string
    {
        $slug = self::slug($domain);
        return "/run/php/php{$phpVersion}-fpm-{$slug}.sock";
    }

    /**
     * Path to pool configuration file.
     */
    public static function poolConfigPath(string $domain, string $phpVersion = '8.3'): string
    {
        $slug = self::slug($domain);
        return "/etc/php/{$phpVersion}/fpm/pool.d/{$slug}.conf";
    }

    /**
     * Ensure dedicated Linux user exists and www-data is in its group.
     */
    public static function ensureIsolatedUser(string $domain, string $basePath): string
    {
        $username = self::siteUser($domain);
        $safeUser = escapeshellarg($username);
        $safePath = escapeshellarg($basePath);

        self::executeSudo("id -u {$safeUser} >/dev/null 2>&1 || useradd -r -s /usr/sbin/nologin -d {$safePath} {$safeUser}");
        self::executeSudo("usermod -aG {$safeUser} www-data");

        return $username;
    }

    /**
     * Create isolated ondemand PHP-FPM pool for the domain.
     */
    public static function createOrUpdatePool(string $domain, string $basePath, string $phpVersion = '8.3'): bool
    {
        $slug = self::slug($domain);
        $username = self::siteUser($domain);
        $sockPath = self::socketPath($domain, $phpVersion);
        $poolFile = self::poolConfigPath($domain, $phpVersion);

        $poolDir = "/etc/php/{$phpVersion}/fpm/pool.d";
        if (!File::exists($poolDir)) {
            Log::warning("PHP pool directory does not exist: {$poolDir}");
            return false;
        }

        $config = <<<CONF
[{$slug}]
user = {$username}
group = {$username}
listen = {$sockPath}
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = ondemand
pm.max_children = 3
pm.process_idle_timeout = 10s
pm.max_requests = 500
request_terminate_timeout = 300s

php_admin_value[open_basedir] = {$basePath}/:/tmp/:/dev/urandom
php_admin_value[disable_functions] = exec,system,passthru,shell_exec,proc_open,popen,dl,show_source
php_admin_value[max_execution_time] = 300
php_admin_value[memory_limit] = 256M
CONF;

        $tempPath = "/tmp/pool_{$slug}_" . time() . ".conf";
        file_put_contents($tempPath, $config);

        self::executeSudo("mv " . escapeshellarg($tempPath) . " " . escapeshellarg($poolFile));
        self::executeSudo("chmod 644 " . escapeshellarg($poolFile));
        self::executeSudo("systemctl reload php{$phpVersion}-fpm");

        Log::info("Created isolated PHP pool for {$domain} at {$poolFile}");
        return true;
    }

    /**
     * Secure directory permissions for tenant isolation.
     */
    public static function securePath(string $basePath, string $domain): void
    {
        $username = self::siteUser($domain);
        $safeUser = escapeshellarg($username);
        $safePath = escapeshellarg($basePath);

        self::executeSudo("chown -R {$safeUser}:{$safeUser} {$safePath}");
        self::executeSudo("find {$safePath} -type d -exec chmod 750 {} \\;");
        self::executeSudo("find {$safePath} -type f -not -path '*/node_modules/*' -not -path '*/vendor/*' -exec chmod 640 {} \\;");

        // Grant Nginx (www-data) read and traverse access via ACL
        self::executeSudo("setfacl -R -m u:www-data:rx {$safePath}");
        self::executeSudo("setfacl -R -d -m u:www-data:rx {$safePath}");

        // Ensure all .bin executables and symlink targets remain executable
        self::executeSudo("find {$safePath} -name '.bin' -type d -exec sh -c 'for d; do for f in \"\$d\"/*; do [ -e \"\$f\" ] && chmod +x \"\$(readlink -f \"\$f\")\"; done; done' _ {} + 2>/dev/null");
        self::executeSudo("find {$safePath} -name 'vendor' -type d -path '*/vendor' -exec sh -c 'for d; do [ -d \"\$d/bin\" ] && chmod -R +x \"\$d/bin\"; done' _ {} + 2>/dev/null");

        // Strictly lock down .env if present (strip ACLs and lock to 600)
        $envPath = rtrim($basePath, '/') . '/.env';
        if (file_exists($envPath)) {
            $safeEnv = escapeshellarg($envPath);
            self::executeSudo("setfacl -b {$safeEnv}");
            self::executeSudo("chmod 600 {$safeEnv}");
        }

        // Keep storage writable if Laravel
        $storagePath = rtrim($basePath, '/') . '/storage';
        if (is_dir($storagePath)) {
            self::executeSudo("chmod -R 775 " . escapeshellarg($storagePath));
        }

        $bootstrapCache = rtrim($basePath, '/') . '/bootstrap/cache';
        if (is_dir($bootstrapCache)) {
            self::executeSudo("chmod -R 775 " . escapeshellarg($bootstrapCache));
        }
    }

    /**
     * Delete isolated PHP pool configuration for domain.
     */
    public static function deletePool(string $domain): void
    {
        $slug = self::slug($domain);
        foreach (['8.2', '8.3'] as $version) {
            $poolFile = "/etc/php/{$version}/fpm/pool.d/{$slug}.conf";
            if (file_exists($poolFile)) {
                self::executeSudo("rm -f " . escapeshellarg($poolFile));
                self::executeSudo("systemctl reload php{$version}-fpm");
                Log::info("Deleted PHP pool {$poolFile}");
            }
        }
    }

    /**
     * Remove pool for a specific PHP version (e.g. during version switch).
     */
    public static function removePoolForVersion(string $domain, string $phpVersion): void
    {
        $slug = self::slug($domain);
        $poolFile = "/etc/php/{$phpVersion}/fpm/pool.d/{$slug}.conf";
        if (file_exists($poolFile)) {
            self::executeSudo("rm -f " . escapeshellarg($poolFile));
            self::executeSudo("systemctl reload php{$phpVersion}-fpm");
            Log::info("Removed PHP {$phpVersion} pool {$poolFile}");
        }
    }

    /**
     * Safely read the contents of a file (such as .env or wp-config.php)
     * even if owned by an isolated site user with 600 permissions.
     */
    public static function readFile(string $path): ?string
    {
        if (!file_exists($path)) {
            return null;
        }

        if (is_readable($path)) {
            $content = @file_get_contents($path);
            if ($content !== false) {
                return $content;
            }
        }

        $escaped = escapeshellarg($path);
        $output = @shell_exec("sudo cat {$escaped} 2>/dev/null");
        if ($output !== null && $output !== false && strlen($output) > 0) {
            return $output;
        }

        return null;
    }

    /**
     * Helper to execute sudo command safely with compound command support.
     */
    private static function executeSudo(string $command): void
    {
        $escaped = escapeshellarg($command);
        exec("sudo bash -c {$escaped} 2>&1", $output, $code);
        if ($code !== 0) {
            Log::warning("SiteIsolationService command failed ({$code}): sudo bash -c {$escaped} -> " . implode("\n", $output));
        }
    }
}
