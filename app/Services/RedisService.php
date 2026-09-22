<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class RedisService
{
    const CONF_PATH = '/etc/redis/redis.conf';

    /**
     * Check if redis-server is installed on the host.
     */
    public static function isInstalled(): bool
    {
        $output = [];
        $returnCode = 0;
        exec('which redis-server 2>&1', $output, $returnCode);
        return $returnCode === 0 && !empty($output[0]);
    }

    /**
     * Check the systemd service status of redis-server.
     */
    public static function getServiceStatus(): string
    {
        if (!self::isInstalled()) {
            return 'not_installed';
        }

        $output = [];
        $returnCode = 0;
        exec('systemctl is-active redis-server 2>&1', $output, $returnCode);
        $status = trim($output[0] ?? 'unknown');

        if ($status === 'active') {
            return 'running';
        } elseif ($status === 'inactive' || $status === 'failed') {
            return 'stopped';
        }

        return $status;
    }

    /**
     * Check if redis-server is enabled on boot.
     */
    public static function isEnabled(): bool
    {
        $output = [];
        $returnCode = 0;
        exec('systemctl is-enabled redis-server 2>&1', $output, $returnCode);
        return trim($output[0] ?? '') === 'enabled';
    }

    /**
     * Control redis-server systemd service.
     */
    public static function serviceAction(string $action): array
    {
        $allowed = ['start', 'stop', 'restart', 'enable', 'disable'];
        if (!in_array($action, $allowed)) {
            return ['success' => false, 'message' => 'Invalid action'];
        }

        $cmd = "sudo systemctl {$action} redis-server 2>&1";
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            return [
                'success' => false,
                'message' => "Failed to {$action} redis-server: " . implode(' ', $output)
            ];
        }

        return [
            'success' => true,
            'message' => "Redis service {$action}ed successfully",
            'status' => self::getServiceStatus()
        ];
    }

    /**
     * Install redis-server, redis-tools, and php-redis extensions.
     */
    public static function install(): array
    {
        $log = [];

        // 1. Install redis-server and redis-tools
        $installCmd = "sudo DEBIAN_FRONTEND=noninteractive apt-get update && sudo DEBIAN_FRONTEND=noninteractive apt-get install -y redis-server redis-tools 2>&1";
        exec($installCmd, $output, $returnCode);
        $log[] = implode("\n", $output);

        if ($returnCode !== 0) {
            return [
                'success' => false,
                'message' => 'Failed to install redis-server package.',
                'log' => implode("\n", $log)
            ];
        }

        // 2. Install php-redis extension for all installed PHP versions
        $phpVersions = ['8.1', '8.2', '8.3', '8.4'];
        foreach ($phpVersions as $ver) {
            if (is_dir("/etc/php/{$ver}")) {
                exec("sudo DEBIAN_FRONTEND=noninteractive apt-get install -y php{$ver}-redis 2>&1", $phpOut, $phpCode);
                $log[] = "PHP {$ver} redis extension: " . ($phpCode === 0 ? 'Installed' : 'Skipped/Failed');
                // Reload FPM if active
                exec("sudo systemctl reload php{$ver}-fpm 2>/dev/null || true");
            }
        }

        // 3. Enable and start redis-server
        exec("sudo systemctl enable redis-server && sudo systemctl start redis-server 2>&1", $sysOut, $sysCode);
        $log[] = implode("\n", $sysOut);

        // 4. Default configuration hardening: ensure maxmemory-policy allkeys-lru
        self::updateConfigFile('maxmemory-policy', 'allkeys-lru');
        self::updateConfigFile('maxmemory', '256mb');
        exec("sudo systemctl restart redis-server 2>/dev/null || true");

        return [
            'success' => true,
            'message' => 'Redis installed and configured successfully!',
            'log' => implode("\n", $log),
            'status' => self::getServiceStatus()
        ];
    }

    /**
     * Get password from /etc/redis/redis.conf if set.
     */
    public static function getPassword(): ?string
    {
        if (!file_exists(self::CONF_PATH)) {
            return null;
        }

        $content = @file_get_contents(self::CONF_PATH) ?: '';
        if (preg_match('/^\s*requirepass\s+["\']?([^"\'\r\n\s]+)["\']?/m', $content, $matches)) {
            return $matches[1] ?? null;
        }

        return null;
    }

    /**
     * Execute a redis-cli command securely.
     */
    public static function executeCli(string $args, int $db = 0): array
    {
        $password = self::getPassword();
        $envPrefix = '';
        if (!empty($password)) {
            $escapedPass = escapeshellarg($password);
            $envPrefix = "REDISCLI_AUTH={$escapedPass} ";
        }

        $dbArg = $db > 0 ? "-n " . (int)$db . " " : "";
        $fullCmd = "{$envPrefix}redis-cli {$dbArg}{$args} 2>&1";

        $output = [];
        $returnCode = 0;
        exec($fullCmd, $output, $returnCode);

        $outputText = implode("\n", $output);

        // Filter out warning about password if it appears
        $cleanedOutput = preg_replace('/Warning: Using a password.*?\n/i', '', $outputText);

        return [
            'success' => $returnCode === 0,
            'output' => trim($cleanedOutput),
            'raw' => $output,
            'code' => $returnCode
        ];
    }

    /**
     * Get comprehensive Redis INFO and operational metrics.
     */
    public static function getInfo(): array
    {
        if (self::getServiceStatus() !== 'running') {
            return [
                'running' => false,
                'status' => self::getServiceStatus(),
                'version' => 'N/A',
                'uptime' => '0',
                'memory' => [],
                'stats' => [],
                'clients' => 0,
                'keyspace' => [],
            ];
        }

        $res = self::executeCli('info all');
        if (!$res['success']) {
            // Try standard info
            $res = self::executeCli('info');
        }

        $raw = $res['output'];
        $parsed = [];
        $currentSection = 'general';

        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            if (str_starts_with($line, '#')) {
                $currentSection = strtolower(trim(substr($line, 1)));
                continue;
            }

            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $parsed[$parts[0]] = $parts[1];
            }
        }

        // Parse memory
        $usedMemory = (int)($parsed['used_memory'] ?? 0);
        $maxMemory = (int)($parsed['maxmemory'] ?? 0);
        $usedPeak = (int)($parsed['used_memory_peak'] ?? 0);
        $memFrag = (float)($parsed['mem_fragmentation_ratio'] ?? 1.0);

        $memoryPercent = 0;
        if ($maxMemory > 0) {
            $memoryPercent = min(100, round(($usedMemory / $maxMemory) * 100, 1));
        }

        // Parse hit rate
        $hits = (int)($parsed['keyspace_hits'] ?? 0);
        $misses = (int)($parsed['keyspace_misses'] ?? 0);
        $totalLookups = $hits + $misses;
        $hitRate = $totalLookups > 0 ? round(($hits / $totalLookups) * 100, 1) : 100.0;

        // Parse keyspace (databases)
        $keyspace = [];
        $totalKeys = 0;
        for ($i = 0; $i < 16; $i++) {
            $dbKey = "db{$i}";
            if (isset($parsed[$dbKey])) {
                // Format: keys=12,expires=4,avg_ttl=4294
                $dbInfo = [];
                foreach (explode(',', $parsed[$dbKey]) as $item) {
                    $kv = explode('=', $item, 2);
                    if (count($kv) === 2) {
                        $dbInfo[$kv[0]] = (int)$kv[1];
                    }
                }
                $keysCount = $dbInfo['keys'] ?? 0;
                $totalKeys += $keysCount;
                $keyspace[$dbKey] = [
                    'db' => $i,
                    'keys' => $keysCount,
                    'expires' => $dbInfo['expires'] ?? 0,
                    'avg_ttl' => $dbInfo['avg_ttl'] ?? 0,
                ];
            }
        }

        // Format uptime
        $uptimeSec = (int)($parsed['uptime_in_seconds'] ?? 0);
        $uptimeHuman = self::formatUptime($uptimeSec);

        return [
            'running' => true,
            'status' => 'running',
            'version' => $parsed['redis_version'] ?? 'Unknown',
            'mode' => $parsed['redis_mode'] ?? 'standalone',
            'os' => $parsed['os'] ?? 'Linux',
            'pid' => (int)($parsed['process_id'] ?? 0),
            'port' => (int)($parsed['tcp_port'] ?? 6379),
            'uptime_seconds' => $uptimeSec,
            'uptime_human' => $uptimeHuman,
            'total_keys' => $totalKeys,
            'memory' => [
                'used_bytes' => $usedMemory,
                'used_human' => $parsed['used_memory_human'] ?? self::formatBytes($usedMemory),
                'peak_bytes' => $usedPeak,
                'peak_human' => $parsed['used_memory_peak_human'] ?? self::formatBytes($usedPeak),
                'max_bytes' => $maxMemory,
                'max_human' => $maxMemory > 0 ? self::formatBytes($maxMemory) : 'Unlimited',
                'percentage' => $memoryPercent,
                'fragmentation_ratio' => $memFrag,
                'policy' => $parsed['maxmemory_policy'] ?? 'allkeys-lru'
            ],
            'stats' => [
                'ops_per_sec' => (int)($parsed['instantaneous_ops_per_sec'] ?? 0),
                'total_commands' => (int)($parsed['total_commands_processed'] ?? 0),
                'total_connections' => (int)($parsed['total_connections_received'] ?? 0),
                'hits' => $hits,
                'misses' => $misses,
                'hit_rate' => $hitRate,
            ],
            'clients' => [
                'connected' => (int)($parsed['connected_clients'] ?? 0),
                'blocked' => (int)($parsed['blocked_clients'] ?? 0),
            ],
            'keyspace' => $keyspace
        ];
    }

    /**
     * Read current Redis configuration from /etc/redis/redis.conf and live Redis.
     */
    public static function getConfig(): array
    {
        $defaults = [
            'maxmemory' => '256mb',
            'maxmemory_policy' => 'allkeys-lru',
            'bind' => '127.0.0.1 -::1',
            'protected_mode' => 'yes',
            'port' => 6379,
            'has_password' => false,
            'password' => '',
            'save_snapshots' => true,
            'appendonly' => 'no',
        ];

        if (!file_exists(self::CONF_PATH)) {
            return $defaults;
        }

        $content = @file_get_contents(self::CONF_PATH) ?: '';

        // Extract maxmemory
        if (preg_match('/^\s*maxmemory\s+([^\r\n#\s]+)/m', $content, $m)) {
            $defaults['maxmemory'] = $m[1];
        }

        // Extract maxmemory-policy
        if (preg_match('/^\s*maxmemory-policy\s+([^\r\n#\s]+)/m', $content, $m)) {
            $defaults['maxmemory_policy'] = $m[1];
        }

        // Extract bind
        if (preg_match('/^\s*bind\s+([^\r\n#]+)/m', $content, $m)) {
            $defaults['bind'] = trim($m[1]);
        }

        // Extract protected-mode
        if (preg_match('/^\s*protected-mode\s+([^\r\n#\s]+)/m', $content, $m)) {
            $defaults['protected_mode'] = strtolower($m[1]);
        }

        // Extract port
        if (preg_match('/^\s*port\s+([0-9]+)/m', $content, $m)) {
            $defaults['port'] = (int)$m[1];
        }

        // Extract requirepass
        if (preg_match('/^\s*requirepass\s+["\']?([^"\'\r\n\s]+)["\']?/m', $content, $m)) {
            $defaults['has_password'] = true;
            $defaults['password'] = $m[1];
        }

        // Extract appendonly
        if (preg_match('/^\s*appendonly\s+([^\r\n#\s]+)/m', $content, $m)) {
            $defaults['appendonly'] = strtolower($m[1]);
        }

        // Check live config if service running
        if (self::getServiceStatus() === 'running') {
            $liveMaxMem = self::executeCli('config get maxmemory');
            if ($liveMaxMem['success'] && !empty($liveMaxMem['output'])) {
                $lines = explode("\n", $liveMaxMem['output']);
                $val = trim($lines[1] ?? '');
                if (is_numeric($val) && (int)$val > 0) {
                    $defaults['maxmemory_live'] = self::formatBytes((int)$val);
                }
            }

            $livePolicy = self::executeCli('config get maxmemory-policy');
            if ($livePolicy['success'] && !empty($livePolicy['output'])) {
                $lines = explode("\n", $livePolicy['output']);
                $defaults['maxmemory_policy_live'] = trim($lines[1] ?? $defaults['maxmemory_policy']);
            }
        }

        return $defaults;
    }

    /**
     * Update configuration both live and persistent in /etc/redis/redis.conf.
     */
    public static function updateConfig(array $settings): array
    {
        $appliedLive = [];

        // 1. maxmemory
        if (isset($settings['maxmemory'])) {
            $val = trim($settings['maxmemory']);
            self::updateConfigFile('maxmemory', $val);
            if (self::getServiceStatus() === 'running') {
                self::executeCli("config set maxmemory " . escapeshellarg($val));
                $appliedLive[] = "maxmemory: {$val}";
            }
        }

        // 2. maxmemory-policy
        if (isset($settings['maxmemory_policy'])) {
            $allowedPolicies = [
                'allkeys-lru', 'volatile-lru', 'allkeys-lfu', 'volatile-lfu',
                'allkeys-random', 'volatile-random', 'volatile-ttl', 'noeviction'
            ];
            $policy = trim($settings['maxmemory_policy']);
            if (in_array($policy, $allowedPolicies)) {
                self::updateConfigFile('maxmemory-policy', $policy);
                if (self::getServiceStatus() === 'running') {
                    self::executeCli("config set maxmemory-policy " . escapeshellarg($policy));
                    $appliedLive[] = "maxmemory-policy: {$policy}";
                }
            }
        }

        // 3. bind
        if (isset($settings['bind'])) {
            $bind = trim($settings['bind']);
            if (!empty($bind)) {
                self::updateConfigFile('bind', $bind);
            }
        }

        // 4. protected-mode
        if (isset($settings['protected_mode'])) {
            $pm = in_array(strtolower($settings['protected_mode']), ['yes', 'no']) ? strtolower($settings['protected_mode']) : 'yes';
            self::updateConfigFile('protected-mode', $pm);
            if (self::getServiceStatus() === 'running') {
                self::executeCli("config set protected-mode {$pm}");
                $appliedLive[] = "protected-mode: {$pm}";
            }
        }

        // 5. password
        if (array_key_exists('password', $settings)) {
            $newPass = trim($settings['password']);
            if (empty($newPass)) {
                self::commentOutConfigFile('requirepass');
                if (self::getServiceStatus() === 'running') {
                    self::executeCli('config set requirepass ""');
                    $appliedLive[] = "requirepass: disabled";
                }
            } else {
                self::updateConfigFile('requirepass', $newPass);
                if (self::getServiceStatus() === 'running') {
                    self::executeCli("config set requirepass " . escapeshellarg($newPass));
                    $appliedLive[] = "requirepass: updated";
                }
            }
        }

        return [
            'success' => true,
            'message' => 'Redis configuration saved successfully.',
            'applied_live' => $appliedLive,
            'config' => self::getConfig()
        ];
    }

    /**
     * Scan and search keys safely without blocking Redis.
     */
    public static function getKeys(string $pattern = '*', int $db = 0, int $limit = 100): array
    {
        if (self::getServiceStatus() !== 'running') {
            return ['keys' => [], 'total' => 0];
        }

        $pattern = empty(trim($pattern)) ? '*' : trim($pattern);
        $escapedPattern = escapeshellarg($pattern);

        // Use SCAN to avoid blocking Redis production databases
        $scanCmd = "eval \"local keys = redis.call('scan', 0, 'match', {$escapedPattern}, 'count', {$limit}) return keys[2]\" 0";
        $res = self::executeCli($scanCmd, $db);

        $keysList = [];
        if ($res['success'] && !empty($res['output'])) {
            $lines = explode("\n", $res['output']);
            foreach ($lines as $k) {
                $k = trim($k);
                if (!empty($k)) {
                    $keysList[] = $k;
                }
            }
        } else {
            // Fallback to scan CLI
            $cliScan = self::executeCli("--scan --pattern {$escapedPattern}", $db);
            if ($cliScan['success'] && !empty($cliScan['output'])) {
                $lines = array_slice(explode("\n", $cliScan['output']), 0, $limit);
                foreach ($lines as $k) {
                    $k = trim($k);
                    if (!empty($k)) {
                        $keysList[] = $k;
                    }
                }
            }
        }

        // Fetch type, TTL and memory for each key
        $detailedKeys = [];
        foreach ($keysList as $key) {
            $escapedKey = escapeshellarg($key);
            $typeRes = self::executeCli("type {$escapedKey}", $db);
            $ttlRes = self::executeCli("ttl {$escapedKey}", $db);
            $memRes = self::executeCli("memory usage {$escapedKey}", $db);

            $type = trim($typeRes['output'] ?? 'unknown');
            $ttl = (int)trim($ttlRes['output'] ?? -1);
            $memory = (int)trim($memRes['output'] ?? 0);

            $detailedKeys[] = [
                'key' => $key,
                'type' => $type,
                'ttl' => $ttl,
                'ttl_human' => self::formatTtl($ttl),
                'memory_bytes' => $memory,
                'memory_human' => $memory > 0 ? self::formatBytes($memory) : 'N/A'
            ];
        }

        return [
            'keys' => $detailedKeys,
            'count' => count($detailedKeys),
            'db' => $db,
            'pattern' => $pattern
        ];
    }

    /**
     * Inspect key value with automatic JSON parsing and structured formatting.
     */
    public static function getKeyValue(string $key, int $db = 0): array
    {
        if (self::getServiceStatus() !== 'running') {
            return ['success' => false, 'message' => 'Redis is not running'];
        }

        $escapedKey = escapeshellarg($key);
        $typeRes = self::executeCli("type {$escapedKey}", $db);
        $ttlRes = self::executeCli("ttl {$escapedKey}", $db);
        $type = trim($typeRes['output'] ?? 'none');
        $ttl = (int)trim($ttlRes['output'] ?? -1);

        $value = null;
        $isJson = false;

        switch ($type) {
            case 'string':
                $getRes = self::executeCli("get {$escapedKey}", $db);
                $value = $getRes['output'];
                // Check if JSON
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
                    $isJson = true;
                    $value = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                }
                break;

            case 'hash':
                $hRes = self::executeCli("hgetall {$escapedKey}", $db);
                $lines = explode("\n", $hRes['output']);
                $hashData = [];
                for ($i = 0; $i < count($lines); $i += 2) {
                    if (isset($lines[$i]) && isset($lines[$i + 1])) {
                        $hashData[trim($lines[$i])] = trim($lines[$i + 1]);
                    }
                }
                $value = json_encode($hashData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                $isJson = true;
                break;

            case 'list':
                $lRes = self::executeCli("lrange {$escapedKey} 0 99", $db);
                $listData = array_map('trim', explode("\n", $lRes['output']));
                $value = json_encode($listData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                $isJson = true;
                break;

            case 'set':
                $sRes = self::executeCli("smembers {$escapedKey}", $db);
                $setData = array_map('trim', explode("\n", $sRes['output']));
                $value = json_encode($setData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                $isJson = true;
                break;

            case 'zset':
                $zRes = self::executeCli("zrange {$escapedKey} 0 99 withscores", $db);
                $zLines = explode("\n", $zRes['output']);
                $zData = [];
                for ($i = 0; $i < count($zLines); $i += 2) {
                    if (isset($zLines[$i]) && isset($zLines[$i + 1])) {
                        $zData[] = ['member' => trim($zLines[$i]), 'score' => trim($zLines[$i + 1])];
                    }
                }
                $value = json_encode($zData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                $isJson = true;
                break;

            default:
                $value = "(Empty or unsupported key type: {$type})";
                break;
        }

        return [
            'success' => true,
            'key' => $key,
            'type' => $type,
            'ttl' => $ttl,
            'ttl_human' => self::formatTtl($ttl),
            'value' => $value,
            'is_json' => $isJson
        ];
    }

    /**
     * Delete a single key.
     */
    public static function deleteKey(string $key, int $db = 0): bool
    {
        $escapedKey = escapeshellarg($key);
        $res = self::executeCli("del {$escapedKey}", $db);
        return $res['success'] && (int)trim($res['output']) >= 1;
    }

    /**
     * Delete only keys matching a prefix/pattern (Safe flush for cache tags).
     */
    public static function flushPattern(string $pattern, int $db = 0): array
    {
        $pattern = trim($pattern);
        if (empty($pattern) || $pattern === '*') {
            return ['success' => false, 'message' => 'Use Flush Database to delete all keys.'];
        }

        $escapedPattern = escapeshellarg($pattern);
        // Find keys and delete in batch
        $cmd = "eval \"local keys = redis.call('keys', {$escapedPattern}) for i,k in ipairs(keys) do redis.call('del', k) end return #keys\" 0";
        $res = self::executeCli($cmd, $db);

        $deletedCount = 0;
        if ($res['success']) {
            $deletedCount = (int)trim($res['output']);
        } else {
            // Fallback via scan and xargs
            $cliCmd = "redis-cli -n " . (int)$db . " --scan --pattern {$escapedPattern} | xargs -r redis-cli -n " . (int)$db . " del";
            $fallbackRes = self::executeCli("--scan --pattern {$escapedPattern}", $db);
            if ($fallbackRes['success'] && !empty($fallbackRes['output'])) {
                $keys = explode("\n", trim($fallbackRes['output']));
                foreach ($keys as $k) {
                    if (!empty(trim($k))) {
                        self::deleteKey(trim($k), $db);
                        $deletedCount++;
                    }
                }
            }
        }

        return [
            'success' => true,
            'deleted_count' => $deletedCount,
            'message' => "Successfully deleted {$deletedCount} key(s) matching pattern '{$pattern}'."
        ];
    }

    /**
     * Flush an entire Redis database (db0 - db15).
     */
    public static function flushDb(int $db = 0): array
    {
        $res = self::executeCli("flushdb async", $db);
        return [
            'success' => $res['success'],
            'message' => $res['success'] ? "Database db{$db} flushed successfully." : $res['output']
        ];
    }

    /**
     * Flush all Redis databases.
     */
    public static function flushAll(): array
    {
        $res = self::executeCli("flushall async");
        return [
            'success' => $res['success'],
            'message' => $res['success'] ? "All Redis databases flushed successfully." : $res['output']
        ];
    }

    /**
     * Get slowlog queries.
     */
    public static function getSlowLog(int $limit = 25): array
    {
        if (self::getServiceStatus() !== 'running') {
            return [];
        }

        $res = self::executeCli("slowlog get {$limit}");
        if (!$res['success'] || empty($res['output'])) {
            return [];
        }

        // Parse slowlog raw output into structured items
        $raw = $res['output'];
        $entries = [];
        $blocks = preg_split('/^\d+\)\s+/m', $raw);

        foreach ($blocks as $block) {
            $block = trim($block);
            if (empty($block)) continue;

            $lines = explode("\n", $block);
            // 1) id
            // 2) timestamp
            // 3) execution time in microseconds
            // 4) command arguments array
            $timestamp = null;
            $durationUs = 0;
            $command = '';
            $client = '';

            foreach ($lines as $l) {
                $l = trim($l);
                if (preg_match('/^1\)\s*\(integer\)\s*(\d+)/', $l, $m)) {
                    $timestamp = (int)$m[1];
                } elseif (preg_match('/^2\)\s*\(integer\)\s*(\d+)/', $l, $m)) {
                    $durationUs = (int)$m[1];
                } elseif (preg_match('/"([^"]+)"/', $l, $m)) {
                    $command .= ($command ? ' ' : '') . $m[1];
                }
            }

            if (!empty($command)) {
                $entries[] = [
                    'timestamp' => $timestamp ? date('Y-m-d H:i:s', $timestamp) : 'Now',
                    'duration_ms' => round($durationUs / 1000, 2),
                    'command' => $command
                ];
            }
        }

        return $entries;
    }

    /**
     * Get connected clients list.
     */
    public static function getClients(): array
    {
        if (self::getServiceStatus() !== 'running') {
            return [];
        }

        $res = self::executeCli("client list");
        if (!$res['success'] || empty($res['output'])) {
            return [];
        }

        $clients = [];
        foreach (explode("\n", $res['output']) as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $props = [];
            foreach (explode(' ', $line) as $pair) {
                $kv = explode('=', $pair, 2);
                if (count($kv) === 2) {
                    $props[$kv[0]] = $kv[1];
                }
            }

            $clients[] = [
                'id' => $props['id'] ?? '-',
                'addr' => $props['addr'] ?? 'Unknown',
                'name' => $props['name'] ?? '',
                'age' => self::formatUptime((int)($props['age'] ?? 0)),
                'idle' => self::formatUptime((int)($props['idle'] ?? 0)),
                'cmd' => $props['cmd'] ?? 'idle',
                'db' => $props['db'] ?? 0
            ];
        }

        return $clients;
    }

    // ─────────────────────────────────────────────────────────────
    // Helper Methods for Config Parsing and Formatting
    // ─────────────────────────────────────────────────────────────

    private static function updateConfigFile(string $directive, string $value): void
    {
        if (!file_exists(self::CONF_PATH)) return;

        $content = @file_get_contents(self::CONF_PATH) ?: '';
        $pattern = '/^\s*#?\s*' . preg_quote($directive, '/') . '\s+.*$/m';
        $replacement = "{$directive} {$value}";

        if (preg_match($pattern, $content)) {
            $newContent = preg_replace($pattern, $replacement, $content, 1);
        } else {
            $newContent = rtrim($content) . "\n\n# Nimbus Managed Setting\n{$replacement}\n";
        }

        $temp = tempnam(sys_get_temp_dir(), 'nimbus_redis_');
        file_put_contents($temp, $newContent);
        exec("sudo cp " . escapeshellarg($temp) . " " . escapeshellarg(self::CONF_PATH) . " && sudo chmod 640 " . escapeshellarg(self::CONF_PATH));
        @unlink($temp);
    }

    private static function commentOutConfigFile(string $directive): void
    {
        if (!file_exists(self::CONF_PATH)) return;

        $content = @file_get_contents(self::CONF_PATH) ?: '';
        $pattern = '/^\s*' . preg_quote($directive, '/') . '\s+.*$/m';

        if (preg_match($pattern, $content)) {
            $newContent = preg_replace($pattern, "# {$directive} disabled", $content);
            $temp = tempnam(sys_get_temp_dir(), 'nimbus_redis_');
            file_put_contents($temp, $newContent);
            exec("sudo cp " . escapeshellarg($temp) . " " . escapeshellarg(self::CONF_PATH));
            @unlink($temp);
        }
    }

    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public static function formatUptime(int $seconds): string
    {
        if ($seconds <= 0) return '0s';
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        $parts = [];
        if ($days > 0) $parts[] = "{$days}d";
        if ($hours > 0) $parts[] = "{$hours}h";
        if ($minutes > 0) $parts[] = "{$minutes}m";
        if (empty($parts)) $parts[] = "{$secs}s";

        return implode(' ', $parts);
    }

    public static function formatTtl(int $ttl): string
    {
        if ($ttl === -1) return 'Persistent (No Expiry)';
        if ($ttl === -2) return 'Expired / Missing';
        return self::formatUptime($ttl);
    }
}
