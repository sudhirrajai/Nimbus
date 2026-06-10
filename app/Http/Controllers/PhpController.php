<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;

class PhpController extends Controller
{
    /**
     * PHP version and SAPI type detection
     */
    private function getPhpInfo()
    {
        $output = [];
        exec('php -v 2>&1', $output);
        $version = isset($output[0]) ? preg_replace('/^PHP\s+([0-9.]+).*$/', '$1', $output[0]) : 'Unknown';
        
        // Get SAPI type
        $sapi = php_sapi_name();
        
        return [
            'version' => $version,
            'sapi' => $sapi
        ];
    }

    /**
     * Get the effective max upload size based on php.ini settings
     */
    private function getEffectiveMaxUploadSize()
    {
        // Default values
        $maxUpload = $this->parseSize(ini_get('upload_max_filesize'));
        $maxPost = $this->parseSize(ini_get('post_max_size'));
        
        // Try to read from actual ini files to get the most accurate "target" limits
        try {
            $iniFiles = $this->getIniFiles();
            foreach ($iniFiles as $ini) {
                // We prioritize FPM settings as they affect web uploads
                if (strpos($ini['label'], 'FPM') !== false) {
                    $settings = $this->parseIniFile($ini['path']);
                    if (isset($settings['upload_max_filesize'])) {
                        $maxUpload = max($maxUpload, $this->parseSize($settings['upload_max_filesize']));
                    }
                    if (isset($settings['post_max_size'])) {
                        $maxPost = max($maxPost, $this->parseSize($settings['post_max_size']));
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::warning("Could not parse ini files for sync: " . $e->getMessage());
        }

        // Return the smaller of the two, formatted for Nginx (e.g. 128M)
        $min = min($maxUpload, $maxPost);
        
        // If we still get a very low value, use a sensible default of at least 512M if the user intended higher
        if ($min < 128 * 1024 * 1024) {
            $min = 128 * 1024 * 1024;
        }

        return ceil($min / 1024 / 1024) . 'M';
    }

    /**
     * Parse PHP size strings (like 128M, 1G) to bytes
     */
    private function parseSize($size)
    {
        $size = trim($size);
        if (empty($size)) return 0;
        
        $unit = strtolower(substr($size, -1));
        $value = (float)$size;
        
        switch ($unit) {
            case 'g': $value *= 1024;
            case 'm': $value *= 1024;
            case 'k': $value *= 1024;
                break;
            default:
                // If it's just a number, it's already in bytes
                return (int)$value;
        }
        
        return (int)$value;
    }

    /**
     * Get list of PHP ini files
     */
    private function getIniFiles()
    {
        $iniFiles = [];
        
        // Common PHP ini locations for different SAPIs
        $possiblePaths = [
            '/etc/php/8.2/fpm/php.ini' => 'PHP-FPM (Web)',
            '/etc/php/8.2/cli/php.ini' => 'CLI',
            '/etc/php/8.2/apache2/php.ini' => 'Apache',
            '/etc/php/8.1/fpm/php.ini' => 'PHP-FPM 8.1 (Web)',
            '/etc/php/8.1/cli/php.ini' => 'CLI 8.1',
            '/etc/php/8.3/fpm/php.ini' => 'PHP-FPM 8.3 (Web)',
            '/etc/php/8.3/cli/php.ini' => 'CLI 8.3',
        ];

        foreach ($possiblePaths as $path => $label) {
            if (File::exists($path)) {
                $iniFiles[] = [
                    'path' => $path,
                    'label' => $label,
                    'exists' => true,
                    'writable' => is_writable($path) || $this->canSudoWrite($path)
                ];
            }
        }

        return $iniFiles;
    }

    /**
     * Check if we can write with sudo
     */
    private function canSudoWrite($path)
    {
        // Check if sudo is available and file exists
        $output = [];
        $returnCode = 0;
        exec("sudo test -w " . escapeshellarg($path) . " && echo 'writable'", $output, $returnCode);
        return $returnCode === 0 && isset($output[0]) && $output[0] === 'writable';
    }

    /**
     * Display PHP INI editor
     */
    public function index()
    {
        return Inertia::render('PHPINI/Index');
    }

    /**
     * Get PHP info and settings
     */
    public function getInfo()
    {
        try {
            $phpInfo = $this->getPhpInfo();
            $iniFiles = $this->getIniFiles();
            
            // Get current important settings
            $currentSettings = $this->getCurrentSettings();
            
            return response()->json([
                'php' => $phpInfo,
                'iniFiles' => $iniFiles,
                'currentSettings' => $currentSettings
            ]);
        } catch (\Exception $e) {
            \Log::error("PHP Info error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get current PHP settings from both runtime and FPM ini file
     */
    private function getCurrentSettings()
    {
        // Get runtime settings (what PHP is currently using)
        $runtimeSettings = [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_execution_time' => ini_get('max_execution_time'),
            'max_input_time' => ini_get('max_input_time'),
            'memory_limit' => ini_get('memory_limit'),
            'max_input_vars' => ini_get('max_input_vars'),
            'file_uploads' => ini_get('file_uploads'),
            'display_errors' => ini_get('display_errors'),
            'error_reporting' => ini_get('error_reporting'),
            'date.timezone' => ini_get('date.timezone'),
            'session.gc_maxlifetime' => ini_get('session.gc_maxlifetime'),
            'opcache.enable' => ini_get('opcache.enable'),
        ];

        // Also try to read from FPM php.ini file to show pending changes
        try {
            $iniFiles = $this->getIniFiles();
            $fpmIni = collect($iniFiles)->first(function($ini) {
                return strpos($ini['label'], 'FPM') !== false;
            });

            if ($fpmIni && isset($fpmIni['path'])) {
                $fileSettings = $this->parseIniFile($fpmIni['path']);
                
                // Merge with runtime settings, preferring file settings for display
                foreach ($runtimeSettings as $key => $value) {
                    if (isset($fileSettings[$key])) {
                        $runtimeSettings[$key] = $fileSettings[$key];
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::warning("Could not read FPM ini file: " . $e->getMessage());
        }

        return $runtimeSettings;
    }

    /**
     * Parse specific settings from php.ini file
     */
    private function parseIniFile($path)
    {
        $settings = [];
        
        try {
            $content = $this->readFileWithSudo($path);
            $lines = explode("\n", $content);
            
            $settingsToFind = [
                'upload_max_filesize',
                'post_max_size',
                'max_execution_time',
                'max_input_time',
                'memory_limit',
                'max_input_vars',
                'file_uploads',
                'display_errors',
                'error_reporting',
                'date.timezone',
                'session.gc_maxlifetime',
                'opcache.enable',
            ];

            foreach ($lines as $line) {
                $trimmedLine = trim($line);
                
                // Skip comments and empty lines
                if (empty($trimmedLine) || $trimmedLine[0] === ';') {
                    continue;
                }
                
                // Parse setting = value
                foreach ($settingsToFind as $setting) {
                    if (preg_match('/^' . preg_quote($setting, '/') . '\s*=\s*(.+)$/i', $trimmedLine, $matches)) {
                        $settings[$setting] = trim($matches[1]);
                        break;
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error("Error parsing ini file: " . $e->getMessage());
        }

        return $settings;
    }

    /**
     * Read ini file content
     */
    public function readIni(Request $request)
    {
        try {
            $request->validate([
                'path' => 'required|string'
            ]);

            $path = $request->input('path');

            // Security check - only allow php.ini files
            if (!preg_match('/^\/etc\/php\/[\d.]+\/(fpm|cli|apache2)\/php\.ini$/', $path)) {
                return response()->json(['error' => 'Invalid ini file path'], 403);
            }

            if (!File::exists($path)) {
                return response()->json(['error' => 'File not found'], 404);
            }

            // Read file content (may need sudo)
            $content = $this->readFileWithSudo($path);

            return response()->json([
                'content' => $content,
                'path' => $path
            ]);
        } catch (\Exception $e) {
            \Log::error("Read INI error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Read file with sudo if needed
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
     * Save ini file content
     */
    public function saveIni(Request $request)
    {
        try {
            $request->validate([
                'path' => 'required|string',
                'content' => 'required|string'
            ]);

            $path = $request->input('path');
            $content = $request->input('content');

            // Security check - only allow php.ini files
            if (!preg_match('/^\/etc\/php\/[\d.]+\/(fpm|cli|apache2)\/php\.ini$/', $path)) {
                return response()->json(['error' => 'Invalid ini file path'], 403);
            }

            if (!File::exists($path)) {
                return response()->json(['error' => 'File not found'], 404);
            }

            // Create backup
            $backupPath = $path . '.backup.' . date('Y-m-d-His');
            $this->executeSudoCommand("cp " . escapeshellarg($path) . " " . escapeshellarg($backupPath));

            // Write content to temp file then move
            $tempFile = tempnam(sys_get_temp_dir(), 'phpini_');
            File::put($tempFile, $content);
            
            $this->executeSudoCommand("cp " . escapeshellarg($tempFile) . " " . escapeshellarg($path));
            $this->executeSudoCommand("chmod 644 " . escapeshellarg($path));
            
            unlink($tempFile);

            return response()->json([
                'message' => 'PHP ini file saved successfully',
                'backup' => $backupPath
            ]);
        } catch (\Exception $e) {
            \Log::error("Save INI error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Restart PHP-FPM service (nginx + PHP-FPM setup)
     * FIXED: Use at command to schedule restart after response is sent
     */
    public function restartPhp(Request $request)
    {
        try {
            $request->validate([
                'version' => 'nullable|string'
            ]);

            $version = $request->input('version', '8.2');
            
            // Validate version format
            if (!preg_match('/^[0-9]+\.[0-9]+$/', $version)) {
                return response()->json(['error' => 'Invalid PHP version format'], 400);
            }

            // Find the actual PHP-FPM service name
            $fpmService = $this->findPhpFpmService($version);
            
            if (!$fpmService) {
                return response()->json([
                    'error' => "PHP-FPM service for version {$version} not found. Please ensure php{$version}-fpm is installed."
                ], 404);
            }

            // Create a restart script
            $scriptPath = storage_path('app/restart_php_fpm.sh');
            $scriptContent = <<<BASH
#!/bin/bash
sleep 2
sudo systemctl restart {$fpmService}
sudo systemctl reload nginx
BASH;

            File::put($scriptPath, $scriptContent);
            chmod($scriptPath, 0755);

            // Schedule the restart to happen 2 seconds from now using 'at' command
            // This allows the HTTP response to be sent first
            $output = [];
            $returnCode = 0;
            
            // Try using 'at' command (most reliable)
            exec("echo 'bash " . escapeshellarg($scriptPath) . "' | at now + 2 seconds 2>&1", $output, $returnCode);
            
            if ($returnCode !== 0) {
                // Fallback: Use background process with nohup
                exec("nohup bash " . escapeshellarg($scriptPath) . " > /dev/null 2>&1 &");
            }

            // Return success immediately
            return response()->json([
                'message' => "PHP {$version} FPM restart scheduled. Service will restart in 2 seconds."
            ]);
            
        } catch (\Exception $e) {
            \Log::error("Restart PHP error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update a specific setting
     */
    public function updateSetting(Request $request)
    {
        try {
            $request->validate([
                'path' => 'required|string',
                'setting' => 'required|string',
                'value' => 'required|string'
            ]);

            $path = $request->input('path');
            $setting = $request->input('setting');
            $value = $request->input('value');

            // Security check
            if (!preg_match('/^\/etc\/php\/[\d.]+\/(fpm|cli|apache2)\/php\.ini$/', $path)) {
                return response()->json(['error' => 'Invalid ini file path'], 403);
            }

            // Validate setting name (only alphanumeric, underscore, dot)
            if (!preg_match('/^[a-zA-Z0-9_.]+$/', $setting)) {
                return response()->json(['error' => 'Invalid setting name'], 400);
            }

            // Read current content
            $content = $this->readFileWithSudo($path);
            $lines = explode("\n", $content);
            $found = false;
            $newLines = [];

            foreach ($lines as $line) {
                $trimmedLine = trim($line);
                
                // Check if this line contains our setting (either commented or not)
                if (preg_match('/^;?\s*' . preg_quote($setting, '/') . '\s*=/', $trimmedLine)) {
                    $newLines[] = "{$setting} = {$value}";
                    $found = true;
                } else {
                    $newLines[] = $line;
                }
            }

            // If setting not found, add it at the end
            if (!$found) {
                $newLines[] = "";
                $newLines[] = "; Added by Nimbus Panel";
                $newLines[] = "{$setting} = {$value}";
            }

            $newContent = implode("\n", $newLines);

            // Create backup
            $backupPath = $path . '.backup.' . date('Y-m-d-His');
            $this->executeSudoCommand("cp " . escapeshellarg($path) . " " . escapeshellarg($backupPath));

            // Write new content
            $tempFile = tempnam(sys_get_temp_dir(), 'phpini_');
            File::put($tempFile, $newContent);
            
            $this->executeSudoCommand("cp " . escapeshellarg($tempFile) . " " . escapeshellarg($path));
            $this->executeSudoCommand("chmod 644 " . escapeshellarg($path));
            
            unlink($tempFile);

            return response()->json([
                'message' => "Setting '{$setting}' updated successfully",
                'backup' => $backupPath
            ]);
        } catch (\Exception $e) {
            \Log::error("Update setting error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Execute sudo command helper
     */
    private function executeSudoCommand($command)
    {
        $output = [];
        $returnCode = 0;

        \Log::debug("Executing sudo command: sudo $command");
        exec("sudo $command 2>&1", $output, $returnCode);

        if ($returnCode !== 0) {
            $errorMsg = "Command execution failed: " . implode("\n", $output);
            \Log::error($errorMsg);
            throw new \Exception($errorMsg);
        }

        return $output;
    }

    /**
     * Find the PHP-FPM service name for a given version
     */
    private function findPhpFpmService($version)
    {
        // Common service name patterns
        $possibleNames = [
            "php{$version}-fpm",           // Ubuntu/Debian standard
            "php-fpm-{$version}",          // Alternative naming
            "php-fpm",                      // Generic PHP-FPM
        ];

        foreach ($possibleNames as $serviceName) {
            $output = [];
            $returnCode = 0;
            
            // Check if service exists using systemctl list-units
            exec("systemctl list-units --type=service --all | grep -i '{$serviceName}' 2>&1", $output, $returnCode);
            
            if (!empty($output)) {
                // Extract the actual service name from the output
                foreach ($output as $line) {
                    if (preg_match('/^\s*(php[0-9.]+-fpm\.service|php-fpm.*\.service)/', trim($line), $matches)) {
                        return str_replace('.service', '', $matches[1]);
                    }
                }
            }
            
            // Try checking directly
            exec("systemctl status {$serviceName} 2>&1", $output, $returnCode);
            if ($returnCode === 0 || $returnCode === 3) { // 0 = running, 3 = stopped but exists
                return $serviceName;
            }
        }

        // Last resort: try to find any PHP-FPM service
        $output = [];
        exec("systemctl list-units --type=service --all | grep -i 'php.*fpm' 2>&1", $output);
        
        if (!empty($output)) {
            foreach ($output as $line) {
                if (preg_match('/^\s*(php[0-9.]+-fpm)/', trim($line), $matches)) {
                    return $matches[1];
                }
            }
        }

        return null;
    }

    /**
     * Sync PHP upload limits to Nginx client_max_body_size
     */
    public function syncNginxLimits(Request $request)
    {
        try {
            $maxSize = $this->getEffectiveMaxUploadSize();
            
            // 1. Update global nginx.conf if possible
            $nginxConf = '/etc/nginx/nginx.conf';
            if (File::exists($nginxConf)) {
                $content = $this->readFileWithSudo($nginxConf);
                
                // Check if client_max_body_size exists in http block
                if (preg_match('/client_max_body_size\s+[^;]+;/', $content)) {
                    $content = preg_replace('/client_max_body_size\s+[^;]+;/', "client_max_body_size {$maxSize};", $content);
                } else {
                    // Try to insert after 'http {'
                    $content = preg_replace('/http\s*\{/', "http {\n    client_max_body_size {$maxSize};", $content);
                }
                
                // Save back to nginx.conf
                $tempFile = tempnam(sys_get_temp_dir(), 'nginxconf_');
                File::put($tempFile, $content);
                $this->executeSudoCommand("cp " . escapeshellarg($tempFile) . " " . escapeshellarg($nginxConf));
                unlink($tempFile);
            }

            // 2. Also update all site configs in sites-available to ensure consistency
            $sitesDir = '/etc/nginx/sites-available/';
            if (File::exists($sitesDir)) {
                $files = File::files($sitesDir);
                foreach ($files as $file) {
                    $path = $file->getRealPath();
                    $siteContent = $this->readFileWithSudo($path);
                    
                    if (preg_match('/client_max_body_size\s+[^;]+;/', $siteContent)) {
                        $siteContent = preg_replace('/client_max_body_size\s+[^;]+;/', "client_max_body_size {$maxSize};", $siteContent);
                        
                        $tempSiteFile = tempnam(sys_get_temp_dir(), 'nginxsite_');
                        File::put($tempSiteFile, $siteContent);
                        $this->executeSudoCommand("cp " . escapeshellarg($tempSiteFile) . " " . escapeshellarg($path));
                        unlink($tempSiteFile);
                    }
                }
            }

            // 3. Reload Nginx
            $this->executeSudoCommand("nginx -t");
            $this->executeSudoCommand("systemctl reload nginx");

            return response()->json([
                'message' => "Successfully synchronized upload limits. Nginx client_max_body_size set to {$maxSize}.",
                'size' => $maxSize
            ]);
        } catch (\Exception $e) {
            \Log::error("Sync Nginx Limits error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get installed and available PHP versions
     */
    public function getPhpVersions()
    {
        try {
            $supportedVersions = ['7.4', '8.0', '8.1', '8.2', '8.3', '8.4'];
            $versions = [];
            
            foreach ($supportedVersions as $v) {
                $installed = File::exists("/etc/php/{$v}/fpm");
                $active = false;
                if ($installed) {
                    $output = [];
                    exec("systemctl is-active php{$v}-fpm 2>&1", $output);
                    $active = isset($output[0]) && trim($output[0]) === 'active';
                }
                
                $versions[] = [
                    'version' => $v,
                    'installed' => $installed,
                    'active' => $active,
                    'service' => "php{$v}-fpm"
                ];
            }
            
            // Get current global/default PHP version setting
            $globalPhp = '8.2';
            try {
                $globalSetting = \App\Models\Setting::where('key', 'global_php_version')->first();
                if ($globalSetting && $globalSetting->value) {
                    $globalPhp = $globalSetting->value;
                }
            } catch (\Exception $e) {
                // Ignore
            }
            
            return response()->json([
                'versions' => $versions,
                'global_php_version' => $globalPhp
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Start background installation of a PHP version
     */
    public function installPhpVersion(Request $request)
    {
        try {
            $request->validate([
                'version' => 'required|string|regex:/^[0-9]+\.[0-9]+$/'
            ]);

            $version = $request->input('version');

            // Validate that version is one of supported
            if (!in_array($version, ['7.4', '8.0', '8.1', '8.2', '8.3', '8.4'])) {
                return response()->json(['error' => 'Unsupported PHP version'], 400);
            }

            if (File::exists("/etc/php/{$version}/fpm")) {
                return response()->json(['error' => "PHP {$version} is already installed."], 400);
            }

            $lockFile = storage_path('logs/php_install.lock');
            if (file_exists($lockFile)) {
                $lockContent = file_get_contents($lockFile);
                return response()->json([
                    'error' => "Another PHP installation is in progress: {$lockContent}. Please wait for it to complete."
                ], 409);
            }

            // Create lock file
            file_put_contents($lockFile, "Installing PHP {$version}");

            // Log file paths
            $logFile = storage_path('logs/php_install.log');
            file_put_contents($logFile, "=== PHP {$version} Installation Started ===\n");
            file_put_contents($logFile, "Time: " . date('Y-m-d H:i:s') . "\n\n", FILE_APPEND);

            // Script path
            $scriptPath = storage_path('app/install_php_' . $version . '.sh');

            // Build bash script content
            $scriptContent = <<<BASH
#!/bin/bash
export DEBIAN_FRONTEND=noninteractive
export NEEDRESTART_MODE=a
export NEEDRESTART_SUSPEND=1

# Prevent services from restarting automatically during apt-get
echo -e '#!/bin/sh\\nexit 101' | sudo tee /usr/sbin/policy-rc.d > /dev/null
sudo chmod +x /usr/sbin/policy-rc.d

# Wait for apt lock
while sudo fuser /var/lib/dpkg/lock-frontend >/dev/null 2>&1 || sudo fuser /var/lib/apt/lists/lock >/dev/null 2>&1; do
    echo "Waiting for other apt process to finish..."
    sleep 2
done

# Clean up broken installations
sudo dpkg --configure -a 2>&1
sudo apt-get install -f -y 2>&1

echo "Checking ondrej/php repository..."
if ! grep -q "^deb .*ondrej/php" /etc/apt/sources.list /etc/apt/sources.list.d/* 2>/dev/null; then
    echo "Adding ondrej/php repository..."
    sudo add-apt-repository -y ppa:ondrej/php 2>&1
fi

echo "Updating package lists..."
sudo apt-get update 2>&1

echo "Installing PHP {$version}..."
sudo apt-get install -y php{$version}-fpm php{$version}-cli php{$version}-mysql php{$version}-curl php{$version}-gd php{$version}-mbstring php{$version}-zip php{$version}-xml php{$version}-intl php{$version}-opcache php{$version}-sqlite3 2>&1

echo "Configuring systemd override for PHP-FPM {$version}..."
sudo mkdir -p "/etc/systemd/system/php{$version}-fpm.service.d"
sudo tee "/etc/systemd/system/php{$version}-fpm.service.d/nimbus.conf" << 'SYSTEMD'
[Service]
ReadWritePaths=/usr/local/nimbus /var/www /usr/share/adminer /etc/nginx /etc/php /etc/supervisor /etc/letsencrypt /etc/postfix /etc/dovecot
SYSTEMD

# Clean up policy-rc.d
sudo rm -f /usr/sbin/policy-rc.d

sudo systemctl daemon-reload 2>&1
sudo systemctl enable php{$version}-fpm 2>&1
sudo systemctl restart php{$version}-fpm 2>&1

echo "PHP {$version} installation complete!"
echo "done" > /tmp/nimbus_php_install_done
BASH;

            File::put($scriptPath, $scriptContent);
            chmod($scriptPath, 0755);

            // Execute script in background using at command (runs outside PHP-FPM systemd namespace)
            exec("echo 'bash " . escapeshellarg($scriptPath) . " > " . escapeshellarg($logFile) . " 2>&1' | at now 2>&1");

            return response()->json([
                'success' => true,
                'message' => "Installation of PHP {$version} started in background."
            ]);

        } catch (\Exception $e) {
            \Log::error("PHP Install error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Poll PHP installation status
     */
    public function getPhpInstallStatus()
    {
        $lockFile = storage_path('logs/php_install.lock');
        $logFile = storage_path('logs/php_install.log');
        
        $status = 'idle';
        if (file_exists($lockFile)) {
            $status = 'installing';
        }
        
        if ($status === 'installing' && file_exists('/tmp/nimbus_php_install_done')) {
            @unlink($lockFile);
            @unlink('/tmp/nimbus_php_install_done');
            $status = 'idle';
        }
        
        $log = '';
        if (file_exists($logFile)) {
            $log = file_get_contents($logFile);
        }
        
        return response()->json([
            'status' => $status,
            'log' => $log
        ]);
    }

    /**
     * List extensions for a specific PHP version
     */
    public function getExtensions($version)
    {
        try {
            if (!preg_match('/^[0-9]+\.[0-9]+$/', $version)) {
                return response()->json(['error' => 'Invalid PHP version format'], 400);
            }
            
            // Run phpX.Y -m to get loaded modules
            $output = [];
            $returnCode = 0;
            exec("php{$version} -m 2>&1", $output, $returnCode);
            
            if ($returnCode !== 0) {
                return response()->json(['error' => "PHP {$version} is not fully installed or CLI binary not found."], 400);
            }
            
            $modules = array_map('strtolower', $output);
            
            $commonExtensions = [
                ['name' => 'mysql', 'package' => 'mysql', 'modules' => ['mysqli', 'pdo_mysql'], 'description' => 'MySQL database support (mysqli, pdo_mysql)'],
                ['name' => 'curl', 'package' => 'curl', 'modules' => ['curl'], 'description' => 'Client URL Library support'],
                ['name' => 'gd', 'package' => 'gd', 'modules' => ['gd'], 'description' => 'Image processing and generation library'],
                ['name' => 'zip', 'package' => 'zip', 'modules' => ['zip'], 'description' => 'Zip archive compression and reading'],
                ['name' => 'mbstring', 'package' => 'mbstring', 'modules' => ['mbstring'], 'description' => 'Multibyte string support (UTF-8, etc.)'],
                ['name' => 'xml', 'package' => 'xml', 'modules' => ['xml', 'simplexml', 'dom'], 'description' => 'XML Parsing and DOM support'],
                ['name' => 'intl', 'package' => 'intl', 'modules' => ['intl'], 'description' => 'Internationalization functions support'],
                ['name' => 'imagick', 'package' => 'imagick', 'modules' => ['imagick'], 'description' => 'ImageMagick image processing support'],
                ['name' => 'redis', 'package' => 'redis', 'modules' => ['redis'], 'description' => 'Redis key-value caching support'],
                ['name' => 'bcmath', 'package' => 'bcmath', 'modules' => ['bcmath'], 'description' => 'Arbitrary precision mathematics support'],
                ['name' => 'soap', 'package' => 'soap', 'modules' => ['soap'], 'description' => 'Simple Object Access Protocol (SOAP) support'],
                ['name' => 'gmp', 'package' => 'gmp', 'modules' => ['gmp'], 'description' => 'GNU Multiple Precision arithmetic support'],
                ['name' => 'sqlite3', 'package' => 'sqlite3', 'modules' => ['sqlite3', 'pdo_sqlite'], 'description' => 'SQLite3 database engine support'],
                ['name' => 'opcache', 'package' => 'opcache', 'modules' => ['zend opcache'], 'description' => 'Zend OPcache bytecode caching support']
            ];
            
            $extensions = [];
            foreach ($commonExtensions as $ext) {
                $installed = false;
                foreach ($ext['modules'] as $m) {
                    if (in_array(strtolower($m), $modules)) {
                        $installed = true;
                        break;
                    }
                }
                
                $extensions[] = [
                    'name' => $ext['name'],
                    'package' => "php{$version}-" . $ext['package'],
                    'description' => $ext['description'],
                    'installed' => $installed
                ];
            }
            
            return response()->json(['extensions' => $extensions]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Start background installation of a PHP extension
     */
    public function installExtension(Request $request, $version)
    {
        try {
            $request->validate([
                'extension' => 'required|string|regex:/^[a-zA-Z0-9_-]+$/'
            ]);

            $extension = $request->input('extension');

            if (!preg_match('/^[0-9]+\.[0-9]+$/', $version)) {
                return response()->json(['error' => 'Invalid PHP version format'], 400);
            }

            if (!File::exists("/etc/php/{$version}/fpm")) {
                return response()->json(['error' => "PHP {$version} is not installed."], 400);
            }

            $lockFile = storage_path('logs/php_ext_install.lock');
            if (file_exists($lockFile)) {
                $lockContent = file_get_contents($lockFile);
                return response()->json([
                    'error' => "Another PHP extension installation is in progress: {$lockContent}. Please wait."
                ], 409);
            }

            // Create lock file
            file_put_contents($lockFile, "Installing {$extension} for PHP {$version}");

            // Log file paths
            $logFile = storage_path('logs/php_ext_install.log');
            file_put_contents($logFile, "=== PHP {$version} Extension {$extension} Installation Started ===\n");
            file_put_contents($logFile, "Time: " . date('Y-m-d H:i:s') . "\n\n", FILE_APPEND);

            // Script path
            $scriptPath = storage_path('app/install_php_ext_' . $version . '_' . $extension . '.sh');

            // Build bash script content
            $scriptContent = <<<BASH
#!/bin/bash
export DEBIAN_FRONTEND=noninteractive
export NEEDRESTART_MODE=a
export NEEDRESTART_SUSPEND=1

# Prevent services from restarting automatically during apt-get
echo -e '#!/bin/sh\\nexit 101' | sudo tee /usr/sbin/policy-rc.d > /dev/null
sudo chmod +x /usr/sbin/policy-rc.d

# Wait for apt lock
while sudo fuser /var/lib/dpkg/lock-frontend >/dev/null 2>&1 || sudo fuser /var/lib/apt/lists/lock >/dev/null 2>&1; do
    echo "Waiting for other apt process to finish..."
    sleep 2
done

# Clean up broken installations
sudo dpkg --configure -a 2>&1
sudo apt-get install -f -y 2>&1

echo "Installing extension php{$version}-{$extension}..."
sudo apt-get update 2>&1
sudo apt-get install -y php{$version}-{$extension} 2>&1

# Clean up policy-rc.d
sudo rm -f /usr/sbin/policy-rc.d

echo "Restarting PHP {$version} FPM..."
sudo systemctl restart php{$version}-fpm 2>&1

echo "Extension php{$version}-{$extension} installation complete!"
echo "done" > /tmp/nimbus_php_ext_install_done
BASH;

            File::put($scriptPath, $scriptContent);
            chmod($scriptPath, 0755);

            // Execute script in background using at command (runs outside PHP-FPM systemd namespace)
            exec("echo 'bash " . escapeshellarg($scriptPath) . " > " . escapeshellarg($logFile) . " 2>&1' | at now 2>&1");

            return response()->json([
                'success' => true,
                'message' => "Installation of php{$version}-{$extension} started in background."
            ]);

        } catch (\Exception $e) {
            \Log::error("PHP Extension Install error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Poll PHP extension installation status
     */
    public function getExtensionInstallStatus($version)
    {
        $lockFile = storage_path('logs/php_ext_install.lock');
        $logFile = storage_path('logs/php_ext_install.log');
        
        $status = 'idle';
        if (file_exists($lockFile)) {
            $status = 'installing';
        }
        
        if ($status === 'installing' && file_exists('/tmp/nimbus_php_ext_install_done')) {
            @unlink($lockFile);
            @unlink('/tmp/nimbus_php_ext_install_done');
            $status = 'idle';
        }
        
        $log = '';
        if (file_exists($logFile)) {
            $log = file_get_contents($logFile);
        }
        
        return response()->json([
            'status' => $status,
            'log' => $log
        ]);
    }

    /**
     * Update the global/default PHP version for all sites
     */
    public function updateGlobalPhpVersion(Request $request)
    {
        try {
            $request->validate([
                'php_version' => 'required|string|regex:/^[0-9]+\.[0-9]+$/'
            ]);

            $phpVersion = $request->php_version;

            // Validate that the version exists
            if (!File::exists("/etc/php/{$phpVersion}/fpm")) {
                return response()->json([
                    'error' => "PHP {$phpVersion} FPM is not installed on this server."
                ], 400);
            }

            // Save version to settings
            \App\Models\Setting::updateOrCreate(
                ['key' => 'global_php_version'],
                ['value' => $phpVersion]
            );

            // Get all website directories
            $basePath = '/var/www/';
            $directories = File::directories($basePath);
            $updatedDomains = [];
            $skippedDomains = [];
            $failedDomains = [];

            foreach ($directories as $dirPath) {
                $domain = basename($dirPath);

                // Exclude system/default directories
                if (in_array(strtolower($domain), ['html', 'default', 'public', 'cgi-bin', 'nimbus'])) {
                    continue;
                }

                // Check if Nginx configuration file exists for this domain
                $configPath = "/etc/nginx/sites-available/{$domain}";
                $output = [];
                exec("sudo test -f " . escapeshellarg($configPath) . " && echo 'exists'", $output);
                if (!isset($output[0]) || $output[0] !== 'exists') {
                    $skippedDomains[] = $domain;
                    continue;
                }

                try {
                    // Create backup
                    $backupPath = $configPath . '.backup.' . date('Y-m-d-His');
                    $this->executeSudoCommand("cp " . escapeshellarg($configPath) . " " . escapeshellarg($backupPath));

                    // Read Nginx config
                    $output = [];
                    exec("sudo cat " . escapeshellarg($configPath) . " 2>/dev/null", $output);
                    $configContent = implode("\n", $output);

                    // Replace fastcgi_pass socket path
                    $pattern = '/(fastcgi_pass\s+unix:)(?:\/var)?(\/run\/php\/php)[0-9.]+(-fpm(?:-nimbus)?\.sock;)/';
                    $replacement = '${1}${2}' . $phpVersion . '${3}';
                    
                    if (!preg_match($pattern, $configContent)) {
                        $pattern = '/(fastcgi_pass\s+unix:[^;]+\.sock;)/';
                        $replacement = "fastcgi_pass unix:/var/run/php/php{$phpVersion}-fpm.sock;";
                    }

                    $newConfigContent = preg_replace($pattern, $replacement, $configContent);

                    // Write to temp file then move to Nginx config directory
                    $tempPath = "/tmp/nginx_{$domain}_" . time() . ".conf";
                    file_put_contents($tempPath, $newConfigContent);
                    
                    $this->executeSudoCommand("cp {$tempPath} {$configPath}");
                    $this->executeSudoCommand("chmod 644 {$configPath}");
                    unlink($tempPath);

                    // Verify configuration
                    try {
                        $this->executeSudoCommand("nginx -t");
                        $this->executeSudoCommand("rm -f " . escapeshellarg($backupPath));
                        $updatedDomains[] = $domain;
                    } catch (\Exception $nginxEx) {
                        // Rollback on config error
                        $this->executeSudoCommand("cp " . escapeshellarg($backupPath) . " " . escapeshellarg($configPath));
                        $this->executeSudoCommand("rm -f " . escapeshellarg($backupPath));
                        $failedDomains[] = "$domain (Nginx test failed)";
                    }
                } catch (\Exception $domainEx) {
                    $failedDomains[] = "$domain (" . $domainEx->getMessage() . ")";
                }
            }

            // Reload Nginx if any updates succeeded
            if (count($updatedDomains) > 0) {
                $this->executeSudoCommand("systemctl reload nginx");
            }

            $message = "Global PHP version set to {$phpVersion}. ";
            if (count($updatedDomains) > 0) {
                $message .= "Successfully updated " . count($updatedDomains) . " site(s). ";
            }
            if (count($failedDomains) > 0) {
                $message .= "Failed for: " . implode(', ', $failedDomains);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'updated' => $updatedDomains,
                'failed' => $failedDomains
            ]);

        } catch (\Exception $e) {
            \Log::error("Failed to update global PHP version: " . $e->getMessage());
            return response()->json([
                'error' => 'Failed to update global PHP version: ' . $e->getMessage()
            ], 500);
        }
    }
}