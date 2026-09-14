<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;

class NginxController extends Controller
{
    private $basePath = '/var/www/';
    private $sitesAvailable = '/etc/nginx/sites-available/';
    private $sitesEnabled = '/etc/nginx/sites-enabled/';

    /**
     * Display Nginx configuration page
     */
    public function index()
    {
        return Inertia::render('Nginx/Index');
    }

    /**
     * Get all domains with their nginx config status
     */
    public function getDomains()
    {
        try {
            if (!File::exists($this->basePath)) {
                return response()->json([
                    'error' => "Base path {$this->basePath} does not exist."
                ], 500);
            }

            $user = auth()->user();
            $directories = collect(File::directories($this->basePath))
                ->map(function ($path) {
                    return basename($path);
                })
                ->filter(function ($name) use ($user) {
                    // Ignore system directories
                    if (in_array(strtolower($name), ['html', 'default', 'public', 'cgi-bin', 'nimbus'])) {
                        return false;
                    }
                    // Filter based on user permissions
                    return $user->hasDomainPermission($name, 'nginx');
                })
                ->map(function ($domain) {
                    $configPath = $this->resolveNginxConfigPath($this->sitesAvailable, $domain);
                    $enabledPath = $this->resolveNginxConfigPath($this->sitesEnabled, $domain);
                    $hasConfig = file_exists($configPath);
                    $isProxy = false;
                    $proxyTarget = null;
                    $proxyPreset = null;

                    if ($hasConfig) {
                        $content = @file_get_contents($configPath) ?: '';
                        if (empty($content) && PHP_OS_FAMILY === 'Linux') {
                            try {
                                $content = $this->readFileWithSudo($configPath);
                            } catch (\Throwable $e) {}
                        }

                        if (str_contains($content, 'proxy_pass') || str_contains($content, 'NIMBUS REVERSE PROXY')) {
                            $isProxy = true;
                            if (preg_match('/proxy_pass\s+([^;]+);/', $content, $m)) {
                                $proxyTarget = trim($m[1]);
                            }
                            if (preg_match('/#\s*Preset:\s*([^\s|]+)/', $content, $pm)) {
                                $proxyPreset = trim($pm[1]);
                            }
                        }
                    }
                    
                    return [
                        'domain' => $domain,
                        'hasConfig' => $hasConfig,
                        'isEnabled' => file_exists($enabledPath),
                        'configPath' => $configPath,
                        'isProxy' => $isProxy,
                        'proxyTarget' => $proxyTarget,
                        'proxyPreset' => $proxyPreset ?: ($isProxy ? 'custom' : null),
                    ];
                })
                ->values();

            return response()->json([
                'domains' => $directories
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to get domains: " . $e->getMessage());
            return response()->json([
                'error' => 'Failed to load domains: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get nginx config content for a domain
     */
    public function getConfig(Request $request)
    {
        try {
            $request->validate([
                'domain' => 'required|string|max:253'
            ]);

            $domain = trim($request->input('domain'));
            
            // Security and Permission validation
            if (!$this->isValidDomain($domain)) {
                return response()->json(['error' => 'Invalid domain name'], 400);
            }

            if (!auth()->user()->hasDomainPermission($domain, 'nginx')) {
                return response()->json(['error' => 'Permission denied for this domain'], 403);
            }

            $configPath = $this->resolveNginxConfigPath($this->sitesAvailable, $domain);

            if (!file_exists($configPath)) {
                return response()->json(['error' => 'Configuration file not found'], 404);
            }

            $content = $this->readFileWithSudo($configPath);

            return response()->json([
                'content' => $content,
                'path' => $configPath,
                'domain' => $domain
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to read nginx config: " . $e->getMessage());
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save nginx config for a domain
     */
    public function saveConfig(Request $request)
    {
        try {
            $request->validate([
                'domain' => 'required|string|max:253',
                'content' => 'required|string'
            ]);

            $domain = trim($request->input('domain'));
            $content = $request->input('content');
            
            // Security and Permission validation
            if (!$this->isValidDomain($domain)) {
                return response()->json(['error' => 'Invalid domain name'], 400);
            }

            if (!auth()->user()->hasDomainPermission($domain, 'nginx')) {
                return response()->json(['error' => 'Permission denied for this domain'], 403);
            }

            $configPath = $this->resolveNginxConfigPath($this->sitesAvailable, $domain);

            if (!file_exists($configPath)) {
                return response()->json(['error' => 'Configuration file not found'], 404);
            }

            // Create backup
            $backupPath = $configPath . '.backup.' . date('Y-m-d-His');
            $this->executeSudoCommand("cp " . escapeshellarg($configPath) . " " . escapeshellarg($backupPath));

            // Write content to temp file then move
            $tempFile = tempnam(sys_get_temp_dir(), 'nginx_');
            File::put($tempFile, $content);
            
            $this->executeSudoCommand("cp " . escapeshellarg($tempFile) . " " . escapeshellarg($configPath));
            $this->executeSudoCommand("chmod 644 " . escapeshellarg($configPath));
            
            unlink($tempFile);

            // Test nginx configuration
            $testResult = $this->testNginxConfig();
            
            if (!$testResult['success']) {
                // Restore backup if test fails
                $this->executeSudoCommand("cp " . escapeshellarg($backupPath) . " " . escapeshellarg($configPath));
                return response()->json([
                    'error' => 'Nginx configuration test failed. Changes reverted.',
                    'details' => $testResult['output']
                ], 400);
            }

            return response()->json([
                'message' => 'Configuration saved successfully',
                'backup' => $backupPath
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to save nginx config: " . $e->getMessage());
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test nginx configuration
     */
    public function testConfig()
    {
        try {
            $result = $this->testNginxConfig();
            
            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Nginx configuration test passed',
                    'output' => $result['output']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Nginx configuration test failed',
                    'output' => $result['output']
                ], 400);
            }
        } catch (\Exception $e) {
            \Log::error("Failed to test nginx config: " . $e->getMessage());
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reload nginx service
     */
    public function reloadNginx()
    {
        // Configuration service availability check
        if (\App\Support\LicenseGuard::isBlocked('critical')) {
            return response()->json(['error' => \App\Support\LicenseGuard::degradedMessage('nginx')], 503);
        }

        try {
            // Check if user has permission to reload nginx
            $user = auth()->user();
            if (!$user->isRootOrAdmin()) {
                // For regular users, check if they have nginx permission for at least one domain
                $hasAnyNginxPerm = $user->websites()->whereJsonContains('permissions', 'nginx')->exists();
                if (!$hasAnyNginxPerm) {
                    return response()->json(['error' => 'You do not have permission to reload Nginx'], 403);
                }
            }

            // Test config first
            $testResult = $this->testNginxConfig();
            
            if (!$testResult['success']) {
                return response()->json([
                    'error' => 'Cannot reload: Nginx configuration test failed',
                    'details' => $testResult['output']
                ], 400);
            }

            // Create reload script (similar to PHP-FPM restart approach)
            $scriptPath = storage_path('app/reload_nginx.sh');
            $scriptContent = <<<BASH
#!/bin/bash
sleep 1
sudo systemctl reload nginx
BASH;

            File::put($scriptPath, $scriptContent);
            chmod($scriptPath, 0755);

            // Try using 'at' command for delayed execution
            $output = [];
            $returnCode = 0;
            exec("echo 'bash " . escapeshellarg($scriptPath) . "' | sudo at now + 1 seconds 2>&1", $output, $returnCode);
            
            if ($returnCode !== 0) {
                // Fallback: Use background process with nohup
                exec("nohup bash " . escapeshellarg($scriptPath) . " > /dev/null 2>&1 &");
            }

            return response()->json([
                'message' => 'Nginx reload scheduled. Service will reload in 1 second.'
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to reload nginx: " . $e->getMessage());
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle domain enabled/disabled status
     */
    public function toggleDomain(Request $request)
    {
        try {
            $request->validate([
                'domain' => 'required|string|max:253',
                'enabled' => 'required|boolean'
            ]);

            $domain = trim($request->input('domain'));
            $enabled = $request->input('enabled');
            
            // Security and Permission validation
            if (!$this->isValidDomain($domain)) {
                return response()->json(['error' => 'Invalid domain name'], 400);
            }

            if (!auth()->user()->hasDomainPermission($domain, 'nginx')) {
                return response()->json(['error' => 'Permission denied for this domain'], 403);
            }

            $configPath = $this->resolveNginxConfigPath($this->sitesAvailable, $domain);
            $enabledPath = $this->resolveNginxConfigPath($this->sitesEnabled, $domain);

            if (!file_exists($configPath)) {
                return response()->json(['error' => 'Configuration file not found'], 404);
            }

            if ($enabled) {
                // Create symlink
                if (!file_exists($enabledPath)) {
                    $this->executeSudoCommand("ln -s " . escapeshellarg($configPath) . " " . escapeshellarg($enabledPath));
                }
            } else {
                // Remove symlink
                if (file_exists($enabledPath)) {
                    $this->executeSudoCommand("rm -f " . escapeshellarg($enabledPath));
                }
            }

            // Test config
            $testResult = $this->testNginxConfig();
            if (!$testResult['success']) {
                // Revert the change
                if ($enabled) {
                    $this->executeSudoCommand("rm -f " . escapeshellarg($enabledPath));
                } else {
                    $this->executeSudoCommand("ln -s " . escapeshellarg($configPath) . " " . escapeshellarg($enabledPath));
                }
                return response()->json([
                    'error' => 'Nginx configuration test failed. Change reverted.',
                    'details' => $testResult['output']
                ], 400);
            }

            return response()->json([
                'message' => $enabled ? 'Domain enabled successfully' : 'Domain disabled successfully',
                'enabled' => $enabled
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to toggle domain: " . $e->getMessage());
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function isValidDomain($domain)
    {
        return preg_match('/^[a-zA-Z0-9][a-zA-Z0-9.-]*[a-zA-Z0-9]$/', $domain) && strlen($domain) <= 253;
    }

    /**
     * Resolve the actual Nginx config path checking various common names
     */
    private function resolveNginxConfigPath($baseDir, $domain)
    {
        $variations = [
            $domain,
            strtolower($domain),
            $domain . '.conf',
            strtolower($domain) . '.conf',
            str_replace('www.', '', strtolower($domain)),
            str_replace('www.', '', strtolower($domain)) . '.conf',
        ];

        foreach ($variations as $file) {
            if (file_exists($baseDir . $file)) {
                return $baseDir . $file;
            }
        }

        return $baseDir . $domain;
    }

    /**
     * Test nginx configuration
     */
    private function testNginxConfig()
    {
        $output = [];
        $returnCode = 0;
        exec("sudo nginx -t 2>&1", $output, $returnCode);
        
        return [
            'success' => $returnCode === 0,
            'output' => implode("\n", $output)
        ];
    }

    /**
     * Read file with sudo
     */
    private function readFileWithSudo($path)
    {
        $escapedPath = escapeshellarg($path);
        $output = [];
        $returnCode = 0;
        
        exec("sudo cat {$escapedPath} 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new \Exception("Failed to read file: " . implode("\n", $output));
        }
        
        return implode("\n", $output);
    }

    /**
     * Get Reverse Proxy status and details for a domain
     */
    public function getProxyStatus(Request $request)
    {
        try {
            $request->validate([
                'domain' => 'required|string|max:253'
            ]);

            $domain = trim($request->input('domain'));
            if (!$this->isValidDomain($domain)) {
                return response()->json(['error' => 'Invalid domain name'], 400);
            }

            if (!auth()->user()->hasDomainPermission($domain, 'nginx')) {
                return response()->json(['error' => 'Permission denied for this domain'], 403);
            }

            $configPath = $this->resolveNginxConfigPath($this->sitesAvailable, $domain);
            if (!file_exists($configPath)) {
                return response()->json(['error' => 'Configuration file not found'], 404);
            }

            $content = $this->readFileWithSudo($configPath);

            $isProxy = str_contains($content, 'proxy_pass') || str_contains($content, 'NIMBUS REVERSE PROXY');
            $targetUrl = 'http://127.0.0.1:3000';
            $preset = 'nodejs';
            $enableWebsocket = true;
            $maxBodySize = '100M';
            $buffering = true;
            $timeout = 600;

            if ($isProxy) {
                if (preg_match('/proxy_pass\s+([^;]+);/', $content, $m)) {
                    $targetUrl = trim($m[1]);
                }
                if (preg_match('/#\s*Preset:\s*([^\s|]+)/', $content, $m)) {
                    $preset = trim($m[1]);
                }
                if (preg_match('/client_max_body_size\s+([^;]+);/', $content, $m)) {
                    $maxBodySize = trim($m[1]);
                }
                if (preg_match('/proxy_buffering\s+(off|on);/', $content, $m)) {
                    $buffering = trim($m[1]) === 'on';
                }
                if (preg_match('/proxy_read_timeout\s+(\d+)s?;/', $content, $m)) {
                    $timeout = (int)$m[1];
                }
                $enableWebsocket = str_contains($content, 'Upgrade $http_upgrade');
            }

            return response()->json([
                'domain' => $domain,
                'is_proxy' => $isProxy,
                'target_url' => $targetUrl,
                'preset' => $preset,
                'enable_websocket' => $enableWebsocket,
                'client_max_body_size' => $maxBodySize,
                'proxy_buffering' => $buffering,
                'proxy_read_timeout' => $timeout,
                'has_pre_proxy_backup' => file_exists($configPath . '.nimbus_pre_proxy.bak')
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to get reverse proxy status: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Apply Reverse Proxy preset configuration to domain
     */
    public function applyReverseProxy(Request $request)
    {
        try {
            $request->validate([
                'domain' => 'required|string|max:253',
                'target_url' => 'required|string|max:255',
                'preset' => 'nullable|string|in:nodejs,python,go,docker,custom',
                'enable_websocket' => 'nullable|boolean',
                'client_max_body_size' => 'nullable|string|max:15',
                'proxy_buffering' => 'nullable|boolean',
                'proxy_read_timeout' => 'nullable|integer|min:10|max:3600',
            ]);

            $domain = trim($request->input('domain'));
            if (!$this->isValidDomain($domain)) {
                return response()->json(['error' => 'Invalid domain name'], 400);
            }

            if (!auth()->user()->hasDomainPermission($domain, 'nginx')) {
                return response()->json(['error' => 'Permission denied for this domain'], 403);
            }

            $configPath = $this->resolveNginxConfigPath($this->sitesAvailable, $domain);
            if (!file_exists($configPath)) {
                return response()->json(['error' => 'Configuration file not found'], 404);
            }

            $rawTarget = trim($request->input('target_url'));
            // Normalize target URL (port number, localhost, unix socket)
            if (is_numeric($rawTarget)) {
                $targetUrl = "http://127.0.0.1:{$rawTarget}";
            } elseif (preg_match('/^:([0-9]+)$/', $rawTarget, $pm)) {
                $targetUrl = "http://127.0.0.1:{$pm[1]}";
            } elseif (!str_starts_with($rawTarget, 'http://') && !str_starts_with($rawTarget, 'https://') && !str_starts_with($rawTarget, 'unix:')) {
                $targetUrl = "http://" . $rawTarget;
            } else {
                $targetUrl = $rawTarget;
            }

            $preset = $request->input('preset', 'custom');
            $enableWs = $request->boolean('enable_websocket', true);
            $maxBodySize = $request->input('client_max_body_size', '100M');
            $buffering = $request->boolean('proxy_buffering', true) ? 'on' : 'off';
            $timeout = (int)$request->input('proxy_read_timeout', 600);

            $currentContent = $this->readFileWithSudo($configPath);

            // 1. Create a safety snapshot and pre-proxy backup if not already present
            $preProxyBak = $configPath . '.nimbus_pre_proxy.bak';
            if (!file_exists($preProxyBak)) {
                $this->executeSudoCommand("cp " . escapeshellarg($configPath) . " " . escapeshellarg($preProxyBak));
            }
            $activeBackup = $configPath . '.backup.' . date('Y-m-d-His');
            $this->executeSudoCommand("cp " . escapeshellarg($configPath) . " " . escapeshellarg($activeBackup));

            // 2. Build the reverse proxy block
            $wsSnippet = $enableWs ? "        proxy_set_header Upgrade \$http_upgrade;\n        proxy_set_header Connection \"upgrade\";\n" : "";
            $nowStr = date('Y-m-d H:i:s');
            
            $proxyBlock = <<<NGINX
    # === NIMBUS REVERSE PROXY START ===
    # Preset: {$preset} | Target: {$targetUrl} | Applied: {$nowStr}
    client_max_body_size {$maxBodySize};

    location / {
        proxy_pass {$targetUrl};
        proxy_http_version 1.1;
{$wsSnippet}        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_cache_bypass \$http_upgrade;
        proxy_buffering {$buffering};
        proxy_read_timeout {$timeout}s;
        proxy_connect_timeout 60s;
        proxy_send_timeout {$timeout}s;
    }
    # === NIMBUS REVERSE PROXY END ===
NGINX;

            $newContent = $currentContent;

            // 3. Replace existing reverse proxy block if present
            if (preg_match('/# === NIMBUS REVERSE PROXY START ===.*?# === NIMBUS REVERSE PROXY END ===/s', $newContent)) {
                $newContent = preg_replace(
                    '/# === NIMBUS REVERSE PROXY START ===.*?# === NIMBUS REVERSE PROXY END ===/s',
                    $proxyBlock,
                    $newContent
                );
            } else {
                // Comment out PHP fastcgi block if present to prevent bypass
                if (preg_match('/location\s*~\s*\\\.php\$\s*\{[^}]*\}/s', $newContent, $phpMatch)) {
                    $commentedPhp = "# NIMBUS_PHP_DISABLED_START\n" . preg_replace('/^/m', '    # ', $phpMatch[0]) . "\n    # NIMBUS_PHP_DISABLED_END";
                    $newContent = str_replace($phpMatch[0], $commentedPhp, $newContent);
                }

                // Replace primary location / { ... }
                if (preg_match('/location\s+\/\s*\{[^}]*\}/s', $newContent, $locMatch)) {
                    $newContent = str_replace($locMatch[0], $proxyBlock, $newContent);
                } else {
                    // Inject before the last closing brace
                    $lastBracePos = strrpos($newContent, '}');
                    if ($lastBracePos !== false) {
                        $newContent = substr_replace($newContent, "\n" . $proxyBlock . "\n}", $lastBracePos, 1);
                    } else {
                        $newContent .= "\n" . $proxyBlock;
                    }
                }
            }

            // 4. Save to temporary file & atomically update
            $tempFile = tempnam(sys_get_temp_dir(), 'nginx_proxy_');
            File::put($tempFile, $newContent);
            $this->executeSudoCommand("cp " . escapeshellarg($tempFile) . " " . escapeshellarg($configPath));
            $this->executeSudoCommand("chmod 644 " . escapeshellarg($configPath));
            @unlink($tempFile);

            // 5. Test configuration
            $testResult = $this->testNginxConfig();
            if (!$testResult['success']) {
                // Rollback immediately
                $this->executeSudoCommand("cp " . escapeshellarg($activeBackup) . " " . escapeshellarg($configPath));
                return response()->json([
                    'error' => 'Reverse proxy configuration test failed. Reverted to previous state.',
                    'details' => $testResult['output']
                ], 400);
            }

            // 6. Reload Nginx
            $this->executeSudoCommand("systemctl reload nginx");

            \App\Models\ActivityLog::log(
                'APPLY_REVERSE_PROXY',
                'Nginx',
                "Configured Reverse Proxy preset '{$preset}' -> {$targetUrl} for {$domain}"
            );

            return response()->json([
                'success' => true,
                'message' => "Reverse proxy to {$targetUrl} configured and reloaded successfully.",
                'target_url' => $targetUrl,
                'preset' => $preset,
                'is_proxy' => true
            ]);

        } catch (\Exception $e) {
            \Log::error("Failed to apply reverse proxy: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove Reverse Proxy and restore standard PHP/Static site config
     */
    public function removeReverseProxy(Request $request)
    {
        try {
            $request->validate([
                'domain' => 'required|string|max:253'
            ]);

            $domain = trim($request->input('domain'));
            if (!$this->isValidDomain($domain)) {
                return response()->json(['error' => 'Invalid domain name'], 400);
            }

            if (!auth()->user()->hasDomainPermission($domain, 'nginx')) {
                return response()->json(['error' => 'Permission denied for this domain'], 403);
            }

            $configPath = $this->resolveNginxConfigPath($this->sitesAvailable, $domain);
            if (!file_exists($configPath)) {
                return response()->json(['error' => 'Configuration file not found'], 404);
            }

            $preProxyBak = $configPath . '.nimbus_pre_proxy.bak';
            $activeBackup = $configPath . '.backup.' . date('Y-m-d-His');
            $this->executeSudoCommand("cp " . escapeshellarg($configPath) . " " . escapeshellarg($activeBackup));

            // Option A: If pre-proxy backup exists, try restoring it
            $restoredFromBak = false;
            if (file_exists($preProxyBak)) {
                $this->executeSudoCommand("cp " . escapeshellarg($preProxyBak) . " " . escapeshellarg($configPath));
                $testResult = $this->testNginxConfig();
                if ($testResult['success']) {
                    $restoredFromBak = true;
                    @unlink($preProxyBak);
                } else {
                    // Fall back to surgical revert if backup had old invalid syntax
                    $this->executeSudoCommand("cp " . escapeshellarg($activeBackup) . " " . escapeshellarg($configPath));
                }
            }

            if (!$restoredFromBak) {
                // Option B: Surgical reversal
                $content = $this->readFileWithSudo($configPath);

                // 1. Uncomment PHP block if previously commented out by Nimbus
                if (str_contains($content, '# NIMBUS_PHP_DISABLED_START')) {
                    $content = preg_replace_callback(
                        '/# NIMBUS_PHP_DISABLED_START\s*\n(.*?)\s*# NIMBUS_PHP_DISABLED_END/s',
                        function ($matches) {
                            return preg_replace('/^\s*#\s?/m', '', $matches[1]);
                        },
                        $content
                    );
                }

                // 2. Replace Reverse Proxy block with standard try_files
                $standardBlock = "    location / {\n        try_files \$uri \$uri/ /index.php?\$query_string;\n    }";
                if (preg_match('/# === NIMBUS REVERSE PROXY START ===.*?# === NIMBUS REVERSE PROXY END ===/s', $content)) {
                    $content = preg_replace(
                        '/# === NIMBUS REVERSE PROXY START ===.*?# === NIMBUS REVERSE PROXY END ===/s',
                        $standardBlock,
                        $content
                    );
                } else {
                    // Fallback replace proxy_pass location block
                    $content = preg_replace(
                        '/location\s+\/\s*\{[^}]*proxy_pass[^}]*\}/s',
                        $standardBlock,
                        $content
                    );
                }

                $tempFile = tempnam(sys_get_temp_dir(), 'nginx_unproxy_');
                File::put($tempFile, $content);
                $this->executeSudoCommand("cp " . escapeshellarg($tempFile) . " " . escapeshellarg($configPath));
                $this->executeSudoCommand("chmod 644 " . escapeshellarg($configPath));
                @unlink($tempFile);

                $testResult = $this->testNginxConfig();
                if (!$testResult['success']) {
                    // Rollback
                    $this->executeSudoCommand("cp " . escapeshellarg($activeBackup) . " " . escapeshellarg($configPath));
                    return response()->json([
                        'error' => 'Reverting reverse proxy failed configuration test. Restored to proxy mode.',
                        'details' => $testResult['output']
                    ], 400);
                }
            }

            // Reload Nginx
            $this->executeSudoCommand("systemctl reload nginx");

            \App\Models\ActivityLog::log(
                'REMOVE_REVERSE_PROXY',
                'Nginx',
                "Disabled Reverse Proxy for {$domain}, restored PHP / standard handler"
            );

            return response()->json([
                'success' => true,
                'message' => "Reverse proxy disabled. Standard PHP/Static handler restored.",
                'is_proxy' => false
            ]);

        } catch (\Exception $e) {
            \Log::error("Failed to remove reverse proxy: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}

