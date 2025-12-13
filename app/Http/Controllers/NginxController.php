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

            $directories = collect(File::directories($this->basePath))
                ->map(function ($path) {
                    return basename($path);
                })
                ->filter(function ($name) {
                    // Ignore system directories
                    return !in_array(strtolower($name), [
                        'html', 'default', 'public', 'cgi-bin', 'nimbus'
                    ]);
                })
                ->map(function ($domain) {
                    $configPath = $this->sitesAvailable . $domain;
                    $enabledPath = $this->sitesEnabled . $domain;
                    
                    return [
                        'domain' => $domain,
                        'hasConfig' => file_exists($configPath),
                        'isEnabled' => file_exists($enabledPath),
                        'configPath' => $configPath,
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

            $domain = strtolower(trim($request->input('domain')));
            
            // Security validation
            if (!$this->isValidDomain($domain)) {
                return response()->json(['error' => 'Invalid domain name'], 400);
            }

            $configPath = $this->sitesAvailable . $domain;

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

            $domain = strtolower(trim($request->input('domain')));
            $content = $request->input('content');
            
            // Security validation
            if (!$this->isValidDomain($domain)) {
                return response()->json(['error' => 'Invalid domain name'], 400);
            }

            $configPath = $this->sitesAvailable . $domain;

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
        try {
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
            exec("echo 'bash " . escapeshellarg($scriptPath) . "' | at now + 1 seconds 2>&1", $output, $returnCode);
            
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

            $domain = strtolower(trim($request->input('domain')));
            $enabled = $request->input('enabled');
            
            if (!$this->isValidDomain($domain)) {
                return response()->json(['error' => 'Invalid domain name'], 400);
            }

            $configPath = $this->sitesAvailable . $domain;
            $enabledPath = $this->sitesEnabled . $domain;

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

    /**
     * Validate domain name format
     */
    private function isValidDomain($domain)
    {
        return preg_match('/^[a-z0-9][a-z0-9.-]*[a-z0-9]$/', $domain) && strlen($domain) <= 253;
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
     * Execute sudo command
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
}
