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
     * Get current PHP settings
     */
    private function getCurrentSettings()
    {
        // Important settings to monitor
        $settings = [
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

            // Restart PHP-FPM
            $this->executeSudoCommand("systemctl restart {$fpmService}");

            // Also reload nginx
            $output = [];
            $returnCode = 0;
            exec("sudo systemctl reload nginx 2>&1", $output, $returnCode);

            return response()->json([
                'message' => "PHP {$version} FPM restarted successfully"
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
}
