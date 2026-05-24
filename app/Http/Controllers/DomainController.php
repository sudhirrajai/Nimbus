<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\UserWebsite;

class DomainController extends Controller
{
    private $basePath = '/var/www/';

    /**
     * Return all domain folders — FAST (no shell commands per domain)
     */
    public function index()
    {
        try {
            if (!File::exists($this->basePath)) {
                return response()->json([
                    'error' => "Base path {$this->basePath} does not exist on this system."
                ], 500);
            }

            $serverIp = $this->getServerIp();
            $user = auth()->user();
            $accessibleDomains = $user->accessibleDomains();

            $directories = collect(File::directories($this->basePath))
                ->map(function ($path) {
                    $domain = basename($path);

                    // Quick nginx config check — just test file existence
                    $nginxConfig = '/etc/nginx/sites-enabled/' . $domain;
                    $configExists = false;
                    $documentRoot = $path;
                    try {
                        $output = [];
                        exec("sudo test -f " . escapeshellarg($nginxConfig) . " && echo 'exists'", $output);
                        $configExists = isset($output[0]) && $output[0] === 'exists';
                    } catch (\Exception $e) {
                        $configExists = false;
                    }

                    if ($configExists) {
                        try {
                            $output = [];
                            exec("sudo cat " . escapeshellarg($nginxConfig) . " 2>/dev/null", $output);
                            $configContent = implode("\n", $output);
                            if (preg_match('/root\s+([^;]+);/', $configContent, $matches)) {
                                $documentRoot = trim($matches[1]);
                            }
                        } catch (\Exception $e) {
                            \Log::warning("Failed to read Nginx config for $domain: " . $e->getMessage());
                        }
                    }

                    return [
                        'name' => $domain,
                        'path' => $path,
                        'document_root' => $documentRoot,
                        'storage' => null,       // Loaded lazily
                        'is_active' => null,     // Loaded lazily
                        'server_ip' => null
                    ];
                })
                ->filter(function ($item) use ($user, $accessibleDomains) {
                    if (in_array(strtolower($item['name']), [
                        'html', 
                        'default', 
                        'public', 
                        'cgi-bin',
                        'nimbus'
                    ])) {
                        return false;
                    }

                    if (!$user->isRoot()) {
                        return in_array($item['name'], $accessibleDomains);
                    }

                    return true;
                })
                ->values();

            return response()->json([
                'domains' => $directories,
                'server_ip' => $serverIp
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load domains: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get expensive details (storage + DNS) for a single domain — called lazily from frontend
     */
    public function getDomainDetails($domain)
    {
        try {
            $path = $this->basePath . $domain;
            $serverIp = $this->getServerIp();

            // Storage usage
            $storage = '0B';
            try {
                $escapedPath = escapeshellarg($path);
                $output = $this->executeSudoCommand("du -sh {$escapedPath}");
                if (!empty($output) && isset($output[0])) {
                    $storage = trim(explode("\t", $output[0])[0]);
                }
            } catch (\Exception $e) {
                \Log::warning("Failed to get storage for $domain: " . $e->getMessage());
            }

            // DNS check — cached per domain for 5 minutes
            $isActive = cache()->remember("domain_dns_{$domain}", 300, function () use ($domain, $serverIp) {
                return $this->checkDomainDns($domain, $serverIp);
            });

            return response()->json([
                'domain' => $domain,
                'storage' => $storage,
                'is_active' => $isActive,
                'server_ip' => $serverIp
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'domain' => $domain,
                'storage' => '?',
                'is_active' => false,
                'server_ip' => ''
            ]);
        }
    }

    /**
     * Get the server's public IP
     */
    private function getServerIp()
    {
        return cache()->remember('server_public_ip', 3600, function () {
            try {
                // Try multiple services in case one is down
                $services = [
                    'https://api.ipify.org',
                    'https://icanhazip.com',
                    'https://ifconfig.me/ip'
                ];

                foreach ($services as $service) {
                    $ip = @file_get_contents($service);
                    if ($ip && filter_var(trim($ip), FILTER_VALIDATE_IP)) {
                        return trim($ip);
                    }
                }
            } catch (\Exception $e) {
                \Log::error("Failed to fetch server public IP: " . $e->getMessage());
            }

            // Fallback to server IP if available
            return request()->server('SERVER_ADDR') ?: '127.0.0.1';
        });
    }

    /**
     * Check if a domain points to the server IP
     */
    private function checkDomainDns($domain, $serverIp)
    {
        try {
            $records = @dns_get_record($domain, DNS_A);
            if (!$records) return false;

            foreach ($records as $record) {
                if (isset($record['ip']) && $record['ip'] === $serverIp) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            \Log::error("DNS check failed for $domain: " . $e->getMessage());
        }
        return false;
    }

    /**
     * Add a new domain
     */
    public function store(Request $request)
    {
        $domain = null;
        $path = null;
        $createdDirs = false;
        $createdNginx = false;
        
        try {
            // Validate domain format
            $request->validate([
                'domain' => [
                    'required',
                    'string',
                    'max:253',
                    'regex:/^[a-z0-9_.-]+$/i'
                ]
            ], [
                'domain.required' => 'Domain name is required',
                'domain.regex' => 'Please enter a valid domain name (e.g., example.com)',
                'domain.max' => 'Domain name is too long (max 253 characters)'
            ]);

            if (!File::exists($this->basePath)) {
                return response()->json([
                    'error' => "Base path {$this->basePath} does not exist on this system."
                ], 500);
            }

            // Check if we have write permissions
            if (!is_writable($this->basePath)) {
                return response()->json([
                    'error' => "Permission denied. Please run: sudo chown -R www-data:www-data {$this->basePath} && sudo chmod -R 755 {$this->basePath}"
                ], 500);
            }

            $domain = trim($request->domain);
            $path = $this->basePath . $domain;

            // Check if domain already exists
            if (File::exists($path)) {
                return response()->json([
                    'error' => 'Domain already exists'
                ], 409);
            }

            // Create folder structure using sudo for proper permissions
            $this->executeSudoCommand("mkdir -p {$path}");
            $this->executeSudoCommand("chown -R www-data:www-data {$path}");
            $this->executeSudoCommand("find {$path} -type d -exec chmod 2775 {} \\;");
            $this->executeSudoCommand("find {$path} -type f -exec chmod 664 {} \\;");
            $createdDirs = true;

            // Create basic index file
            $indexContent = $this->getDefaultIndexContent($domain);
            file_put_contents("$path/index.html", $indexContent);

            // Create .env and .htaccess placeholders
            file_put_contents("$path/.env", "APP_ENV=production\nAPP_DEBUG=false\n");
            file_put_contents("$path/.htaccess", "# Nimbus Control Panel - Default .htaccess\n# Powered by Nimbus\n\nOptions -Indexes\n");

            // Create Nginx configuration
            $this->createNginxConfig($domain);
            $createdNginx = true;

            // Ensure all managed domains have the directories/files their nginx configs expect
            $this->repairManagedDomainStructures();

            // Test Nginx configuration before reloading
            $this->executeSudoCommand("nginx -t");
            
            // Reload Nginx
            $this->executeSudoCommand("systemctl reload nginx");

            // Log the creation
            \Log::info("Domain created: $domain by user " . auth()->id());

            // ─── NEW: Auto-assign domain to user ──────────────────────
            $user = auth()->user();
            if (!$user->isRoot()) {
                // 1. Create database assignment
                UserWebsite::create([
                    'user_id' => $user->id,
                    'domain' => $domain,
                    'permissions' => ['files', 'deployments', 'wordpress', 'database', 'ssl', 'nginx', 'supervisor', 'cron'],
                ]);

                // 2. Grant Linux user ACL access
                if ($user->linux_user) {
                    try {
                        $this->executeSudoCommand("setfacl -R -m u:{$user->linux_user}:rwx " . escapeshellarg($path));
                        $this->executeSudoCommand("setfacl -R -d -m u:{$user->linux_user}:rwx " . escapeshellarg($path));
                        \Log::info("Linux ACLs granted for user {$user->linux_user} on $domain");
                    } catch (\Exception $e) {
                        \Log::error("Failed to set Linux ACLs for {$user->linux_user} on $domain: " . $e->getMessage());
                    }
                }
            }
            // ──────────────────────────────────────────────────────────

            return response()->json([
                'message' => 'Domain created successfully',
                'domain' => $domain
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'error' => $e->validator->errors()->first('domain')
            ], 422);
        } catch (\Exception $e) {
            \Log::error("Failed to create domain: " . $e->getMessage());
            
            // Rollback on error
            if ($domain && $path) {
                $this->rollbackDomainCreation($domain, $path, $createdDirs, $createdNginx);
            }
            
            return response()->json([
                'error' => 'Failed to create domain: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Edit domain → rename folder
     */
    public function update(Request $request, $oldDomain)
    {
        $renamed = false;
        $oldConfigDeleted = false;
        $newConfigCreated = false;
        
        try {
            // Validate domain format
            $request->validate([
                'domain' => [
                    'required',
                    'string',
                    'max:253',
                    'regex:/^[a-z0-9_.-]+$/i'
                ]
            ], [
                'domain.required' => 'Domain name is required',
                'domain.regex' => 'Please enter a valid domain name (e.g., example.com)',
                'domain.max' => 'Domain name is too long (max 253 characters)'
            ]);

            $oldPath = $this->basePath . $oldDomain;
            $newDomain = trim($request->domain);
            $newPath = $this->basePath . $newDomain;

            // Check if old domain exists
            if (!File::exists($oldPath)) {
                return response()->json([
                    'error' => 'Domain not found'
                ], 404);
            }

            // If domain name unchanged, return success
            if ($oldDomain === $newDomain) {
                return response()->json([
                    'message' => 'Domain unchanged',
                    'domain' => $newDomain
                ]);
            }

            // Check if new domain already exists
            if (File::exists($newPath)) {
                return response()->json([
                    'error' => 'New domain name already exists'
                ], 409);
            }

            // Delete old Nginx config
            $this->deleteNginxConfig($oldDomain);
            $oldConfigDeleted = true;

            // Rename the directory
            $this->executeSudoCommand("mv {$oldPath} {$newPath}");
            $this->executeSudoCommand("chown -R www-data:www-data {$newPath}");
            $renamed = true;

            // Create new Nginx config
            $this->createNginxConfig($newDomain);
            $newConfigCreated = true;

            // Ensure all managed domains have the directories/files their nginx configs expect
            $this->repairManagedDomainStructures();

            // Test and reload Nginx
            $this->executeSudoCommand("nginx -t");
            $this->executeSudoCommand("systemctl reload nginx");

            // Log the update
            \Log::info("Domain updated: $oldDomain -> $newDomain by user " . auth()->id());

            return response()->json([
                'message' => 'Domain updated successfully',
                'domain' => $newDomain
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'error' => $e->validator->errors()->first('domain')
            ], 422);
        } catch (\Exception $e) {
            \Log::error("Failed to update domain: " . $e->getMessage());
            
            // Rollback on error
            $this->rollbackDomainUpdate($oldDomain, $newDomain, $oldPath, $newPath, $renamed, $oldConfigDeleted, $newConfigCreated);
            
            return response()->json([
                'error' => 'Failed to update domain: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update domain document root
     */
    public function updateRoot(Request $request, $domain)
    {
        try {
            $request->validate([
                'document_root' => 'required|string|max:255'
            ]);

            $newRoot = rtrim(trim($request->document_root), '/');
            $domainPath = $this->basePath . $domain;
            
            // Basic security checks:
            if (!str_starts_with($newRoot, $domainPath)) {
                return response()->json([
                    'error' => 'Document root must be inside the domain directory (' . $domainPath . ')'
                ], 403);
            }

            // Create directory if it doesn't exist
            if (!File::exists($newRoot)) {
                $this->executeSudoCommand("mkdir -p " . escapeshellarg($newRoot));
                $this->executeSudoCommand("chown -R www-data:www-data " . escapeshellarg($newRoot));
                $this->executeSudoCommand("chmod 2775 " . escapeshellarg($newRoot));
                
                // Add default .htaccess to new root
                $htaccess = $newRoot . '/.htaccess';
                file_put_contents($htaccess, "# Nimbus Control Panel - Root .htaccess\nOptions -Indexes\n");
                $this->executeSudoCommand("chown www-data:www-data " . escapeshellarg($htaccess));
                $this->executeSudoCommand("chmod 664 " . escapeshellarg($htaccess));
            }

            // Update Nginx config
            $nginxConfig = '/etc/nginx/sites-available/' . $domain;
            if (!File::exists($nginxConfig)) {
                return response()->json(['error' => 'Nginx config not found'], 404);
            }

            // Use sudo sed to replace the root line
            $escapedConfig = escapeshellarg($nginxConfig);
            // Replace root /var/www/domain.com[/...]; with root $newRoot;
            $sedCmd = "sed -i -E 's|root\s+[^;]+;|root {$newRoot};|g' {$escapedConfig}";
            $this->executeSudoCommand($sedCmd);

            // Test and reload Nginx
            $this->executeSudoCommand("nginx -t");
            $this->executeSudoCommand("systemctl reload nginx");

            \Log::info("Domain document root updated: $domain to $newRoot by user " . auth()->id());

            return response()->json([
                'message' => 'Document root updated successfully',
                'document_root' => $newRoot
            ]);

        } catch (\Exception $e) {
            \Log::error("Failed to update domain root: " . $e->getMessage());
            return response()->json([
                'error' => 'Failed to update document root: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete domain folder
     */
    public function destroy($domain)
    {
        try {
            \Log::info("Starting deletion process for domain: $domain");
            
            // Sanitize domain name to prevent command injection
            $domain = trim($domain);
            if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $domain)) {
                return response()->json([
                    'error' => 'Invalid domain name format'
                ], 400);
            }
            
            $path = $this->basePath . $domain;
            \Log::info("Domain path: $path");

            // Check if domain exists
            if (!File::exists($path)) {
                \Log::warning("Domain not found: $domain at path $path");
                return response()->json([
                    'error' => 'Domain not found'
                ], 404);
            }

            // Check if this is a protected directory
            $protectedDirs = ['html', 'default', 'public', 'cgi-bin', 'nimbus'];
            if (in_array(strtolower($domain), $protectedDirs)) {
                \Log::error("Attempted to delete protected directory: $domain");
                return response()->json([
                    'error' => 'Cannot delete protected system directory'
                ], 403);
            }

            // Step 1: Delete Nginx symlink first (if it exists)
            $symlinkPath = $this->resolveNginxConfigPath('/etc/nginx/sites-enabled/', $domain);
            $symlinkExists = false;
            try {
                $output = [];
                exec("sudo test -f " . escapeshellarg($symlinkPath) . " && echo 'exists'", $output);
                $symlinkExists = isset($output[0]) && $output[0] === 'exists';
            } catch (\Exception $e) { $symlinkExists = false; }

            if ($symlinkExists) {
                \Log::info("Removing Nginx symlink: $symlinkPath");
                $this->executeSudoCommand("rm -f {$symlinkPath}");
            }

            // Step 2: Delete Nginx config file
            $configPath = $this->resolveNginxConfigPath('/etc/nginx/sites-available/', $domain);
            $configExists = false;
            try {
                $output = [];
                exec("sudo test -f " . escapeshellarg($configPath) . " && echo 'exists'", $output);
                $configExists = isset($output[0]) && $output[0] === 'exists';
            } catch (\Exception $e) { $configExists = false; }

            if ($configExists) {
                \Log::info("Removing Nginx config: $configPath");
                $this->executeSudoCommand("rm -f {$configPath}");
            }

            // Ensure remaining managed domains still have the directories/files their nginx configs expect
            $this->repairManagedDomainStructures();

            // Step 3: Test and reload Nginx configuration
            \Log::info("Testing Nginx configuration...");
            try {
                $this->executeSudoCommand("nginx -t");
                \Log::info("Nginx config test passed");
            } catch (\Exception $e) {
                \Log::error("Nginx config test failed: " . $e->getMessage());
                throw new \Exception("Nginx configuration test failed. Please check your Nginx configuration.");
            }

            \Log::info("Reloading Nginx...");
            $this->executeSudoCommand("systemctl reload nginx");
            \Log::info("Nginx reloaded successfully");

            // Step 4: Delete the domain directory
            \Log::info("Removing domain directory: $path");
            
            // Use force removal with proper escaping
            $escapedPath = escapeshellarg($path);
            $this->executeSudoCommand("rm -rf {$escapedPath}");
            
            // Verify deletion
            if (File::exists($path)) {
                \Log::error("Directory still exists after deletion attempt: $path");
                throw new \Exception("Failed to remove directory - it may be in use or have permission issues");
            }

            \Log::info("Domain deleted successfully: $domain by user " . auth()->id());

            // ─── NEW: Cleanup database assignments ────────────────────
            UserWebsite::where('domain', $domain)->delete();
            \Log::info("UserWebsite assignments removed for $domain");
            // ──────────────────────────────────────────────────────────

            return response()->json([
                'message' => 'Domain deleted successfully',
                'domain' => $domain
            ], 200);

        } catch (\Exception $e) {
            \Log::error("Failed to delete domain $domain: " . $e->getMessage());
            \Log::error("Stack trace: " . $e->getTraceAsString());
            
            return response()->json([
                'error' => 'Failed to delete domain: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate default index.php content
     */
    private function getDefaultIndexContent($domain)
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to $domain | Nimbus</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --secondary: #a855f7;
            --accent: #ec4899;
            --dark: #0f172a;
            --light: #f8fafc;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--dark);
            color: var(--light);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        /* Animated Background Gradients */
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, rgba(15, 23, 42, 0) 50%),
                        radial-gradient(circle at 80% 20%, rgba(236, 72, 153, 0.1) 0%, rgba(15, 23, 42, 0) 40%);
            animation: rotate 30s linear infinite;
            z-index: -1;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 4rem 3rem;
            border-radius: 2.5rem;
            text-align: center;
            max-width: 600px;
            width: 90%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: fadeInScale 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            z-index: 10;
        }

        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.9) translateY(20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .logo {
            font-weight: 800;
            font-size: 1.5rem;
            letter-spacing: -0.05em;
            margin-bottom: 2rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(to right, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            line-height: 1;
            letter-spacing: -0.02em;
            background: linear-gradient(to bottom right, #fff 50%, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        p {
            font-size: 1.25rem;
            color: #94a3b8;
            margin-bottom: 2.5rem;
            line-height: 1.6;
            font-weight: 300;
        }

        .badge {
            display: inline-block;
            padding: 0.5rem 1.25rem;
            background: rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.2);
            border-radius: 100px;
            color: var(--primary);
            font-weight: 600;
            font-size: 0.875rem;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .btn {
            display: inline-block;
            background: var(--light);
            color: var(--dark);
            padding: 1rem 2.5rem;
            border-radius: 1rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
            background: #fff;
        }

        .footer {
            margin-top: 3rem;
            font-size: 0.875rem;
            color: #475569;
            font-weight: 400;
        }

        /* Float animation for the card */
        .card {
            animation: fadeInScale 0.8s cubic-bezier(0.34, 1.56, 0.64, 1), float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge">Success!</div>
        <div class="logo">NIMBUS</div>
        <h1>$domain is Live.</h1>
        <p>Your new digital space is ready and waiting. Log in to the Nimbus panel to start uploading your magic.</p>
        <a href="#" class="btn">Get Started</a>
        <div class="footer">
            Powered by Nimbus Control Panel &bull; Premium Cloud Hosting
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Execute sudo command safely
     */
    private function executeSudoCommand($command)
    {
        $output = [];
        $returnCode = 0;
        
        // Log the command being executed (for debugging)
        \Log::debug("Executing sudo command: sudo $command");
        
        // Execute command with proper error handling
        exec("sudo $command 2>&1", $output, $returnCode);
        
        // Log the output
        $outputStr = implode("\n", $output);
        \Log::debug("Command output: " . $outputStr);
        \Log::debug("Return code: $returnCode");
        
        if ($returnCode !== 0) {
            $errorMsg = "Command failed with exit code $returnCode: $command\nOutput: $outputStr";
            \Log::error($errorMsg);
            throw new \Exception("Command execution failed: " . $outputStr);
        }
        
        return $output;
    }

    /**
     * Get the effective max upload size based on php.ini settings for Nginx
     */
    private function getPhpUploadLimit()
    {
        try {
            $uploadMax = ini_get('upload_max_filesize');
            $postMax = ini_get('post_max_size');
            
            // Helper to convert to MB
            $toMB = function($size) {
                $unit = strtolower(substr($size, -1));
                $value = (int)$size;
                switch ($unit) {
                    case 'g': $value *= 1024; break;
                    case 'm': break; // already in M
                    case 'k': $value /= 1024; break;
                }
                return $value;
            };

            $minMB = min($toMB($uploadMax), $toMB($postMax));
            return $minMB . 'M';
        } catch (\Exception $e) {
            return '128M'; // Fallback
        }
    }

    /**
     * Create Nginx configuration for domain
     */
    private function createNginxConfig($domain)
    {
        $configPath = "/etc/nginx/sites-available/{$domain}";
        $symlinkPath = "/etc/nginx/sites-enabled/{$domain}";
        $domainPath = $this->basePath . $domain;
        $tempPath = "/tmp/nginx_{$domain}_" . time() . ".conf";

        $config = <<<NGINX
server {
    listen 80;
    listen [::]:80;
    
    server_name {$domain} www.{$domain};
    root {$domainPath};
    
    index index.php index.html index.htm;
    
    # Logs
    access_log /var/log/nginx/{$domain}.access.log;
    error_log /var/log/nginx/{$domain}.error.log;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Upload limit (Synced from PHP)
    client_max_body_size {$this->getPhpUploadLimit()};
    
    # PHP handling
    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
    }
    
    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }
    
    # Static files caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
    
    # Try files
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
}
NGINX;

        // Write to temp file first (www-data has permission here)
        file_put_contents($tempPath, $config);
        
        // Move to nginx directory using sudo
        $this->executeSudoCommand("mv {$tempPath} {$configPath}");
        $this->executeSudoCommand("chmod 644 {$configPath}");

        // Create symlink if it doesn't exist
        if (!file_exists($symlinkPath)) {
            $this->executeSudoCommand("ln -s {$configPath} {$symlinkPath}");
        }
    }

    /**
     * Delete Nginx configuration for domain
     */
    private function deleteNginxConfig($domain)
    {
        $configPath = $this->resolveNginxConfigPath('/etc/nginx/sites-available/', $domain);
        $symlinkPath = $this->resolveNginxConfigPath('/etc/nginx/sites-enabled/', $domain);

        // Remove symlink first
        if (file_exists($symlinkPath)) {
            \Log::info("Deleting Nginx symlink: $symlinkPath");
            $this->executeSudoCommand("rm -f {$symlinkPath}");
        }

        // Remove config file
        if (file_exists($configPath)) {
            \Log::info("Deleting Nginx config: $configPath");
            $this->executeSudoCommand("rm -f {$configPath}");
        }
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
            $path = $baseDir . $file;
            $output = [];
            exec("sudo test -f " . escapeshellarg($path) . " && echo 'exists'", $output);
            if (isset($output[0]) && $output[0] === 'exists') {
                return $path;
            }
        }

        return $baseDir . $domain;
    }

    /**
     * Ensure all managed domain folders contain the structure expected by nginx configs.
     */
    public function repairManagedDomainStructures()
    {
        $protectedDirs = ['html', 'default', 'public', 'cgi-bin', 'nimbus'];
        $domainsToRepair = [];

        // 1. Gather all domains from existing directories
        if (File::exists($this->basePath)) {
            foreach (File::directories($this->basePath) as $directory) {
                $name = basename($directory);
                if (!in_array(strtolower($name), $protectedDirs, true)) {
                    $domainsToRepair[] = $name;
                }
            }
        }

        // 2. Gather all domains from Nginx enabled configs
        // Even if the directory was deleted, if Nginx expects it, we must recreate it to prevent crashes!
        $sitesPath = '/etc/nginx/sites-enabled/';
        if (is_dir($sitesPath)) {
            foreach (scandir($sitesPath) as $file) {
                if ($file === '.' || $file === '..') continue;
                $name = str_replace('.conf', '', basename($file));
                if ($name !== 'default' && !empty($name) && !in_array(strtolower($name), $protectedDirs, true)) {
                    $domainsToRepair[] = $name;
                }
            }
        }

        // 3. Repair all unique domains
        $domainsToRepair = array_unique($domainsToRepair);
        foreach ($domainsToRepair as $domain) {
            $this->ensureDomainStructure($this->basePath . $domain);
            
            // ─── NEW: Auto-migrate Nginx logs to system path ─────
            $this->migrateNginxLogsToSystemPath($domain);
            // ──────────────────────────────────────────────────
        }
    }

    /**
     * Migrate old Nginx log paths (inside domain) to system path (/var/log/nginx/)
     * This prevents Nginx from crashing if the user deletes the log folder.
     */
    private function migrateNginxLogsToSystemPath($domain)
    {
        $configPath = "/etc/nginx/sites-available/{$domain}";
        if (!File::exists($configPath)) {
            // Check for variations (.conf, etc)
            $configPath = $this->resolveNginxConfigPath('/etc/nginx/sites-available/', $domain);
            if (!File::exists($configPath)) return;
        }

        try {
            $content = $this->executeSudoCommand("cat " . escapeshellarg($configPath));
            $contentStr = implode("\n", $content);
            $domainPath = $this->basePath . $domain;

            // Pattern for old log paths: access_log /var/www/domain/logs/access.log;
            $oldAccessPattern = "access_log {$domainPath}/logs/access.log;";
            $oldErrorPattern = "error_log {$domainPath}/logs/error.log;";

            if (strpos($contentStr, $oldAccessPattern) !== false || strpos($contentStr, $oldErrorPattern) !== false) {
                \Log::info("Migrating Nginx logs for $domain to system path...");
                
                $newAccess = "access_log /var/log/nginx/{$domain}.access.log;";
                $newError = "error_log /var/log/nginx/{$domain}.error.log;";
                
                $escapedConfig = escapeshellarg($configPath);
                
                // Use sed to replace the lines
                $this->executeSudoCommand("sed -i 's|access_log .*logs/access.log;|{$newAccess}|g' {$escapedConfig}");
                $this->executeSudoCommand("sed -i 's|error_log .*logs/error.log;|{$newError}|g' {$escapedConfig}");
                
                \Log::info("Migration complete for $domain");
            }
        } catch (\Exception $e) {
            \Log::error("Failed to migrate logs for $domain: " . $e->getMessage());
        }
    }

    /**
     * Create the directories and log files referenced by the nginx template.
     */
    private function ensureDomainStructure($domainPath)
    {
        // We no longer create logs/ inside the domain to prevent Nginx crashes if users delete them.
        // Nginx logs are now redirected to /var/log/nginx/
        if (!File::exists($domainPath)) {
            $this->executeSudoCommand('mkdir -p ' . escapeshellarg($domainPath));
        }

        $this->executeSudoCommand('chown -R www-data:www-data ' . escapeshellarg($domainPath));
        $this->executeSudoCommand('chmod 2775 ' . escapeshellarg($domainPath));
        
        // Ensure index.html exists if empty
        $indexFile = $domainPath . '/index.html';
        if (!File::exists($indexFile) && count(File::files($domainPath)) === 0) {
            $domain = basename($domainPath);
            $indexContent = $this->getDefaultIndexContent($domain);
            file_put_contents($indexFile, $indexContent);
            $this->executeSudoCommand('chown www-data:www-data ' . escapeshellarg($indexFile));
            $this->executeSudoCommand('chmod 664 ' . escapeshellarg($indexFile));
        }
    }

    /**
     * Rollback domain creation on error
     */
    private function rollbackDomainCreation($domain, $path, $createdDirs, $createdNginx)
    {
        try {
            \Log::warning("Rolling back domain creation for: $domain");
            
            // Remove Nginx config if it was created
            if ($createdNginx) {
                \Log::info("Removing Nginx configuration for: $domain");
                $this->deleteNginxConfig($domain);
                // Try to reload nginx, but don't fail if it errors
                try {
                    $this->executeSudoCommand("nginx -t && systemctl reload nginx");
                } catch (\Exception $e) {
                    \Log::warning("Failed to reload nginx during rollback: " . $e->getMessage());
                }
            }
            
            // Remove directory if it was created
            if ($createdDirs && File::exists($path)) {
                \Log::info("Removing directory: $path");
                $escapedPath = escapeshellarg($path);
                $this->executeSudoCommand("rm -rf {$escapedPath}");
            }
            
            \Log::info("Rollback completed for: $domain");
        } catch (\Exception $e) {
            // Log rollback failures but don't throw - we're already in error handling
            \Log::error("Rollback failed for domain $domain: " . $e->getMessage());
        }
    }

    /**
     * Rollback domain update on error
     */
    private function rollbackDomainUpdate($oldDomain, $newDomain, $oldPath, $newPath, $renamed, $oldConfigDeleted, $newConfigCreated)
    {
        try {
            \Log::warning("Rolling back domain update: $oldDomain -> $newDomain");
            
            // Remove new Nginx config if created
            if ($newConfigCreated) {
                \Log::info("Removing new Nginx config for: $newDomain");
                $this->deleteNginxConfig($newDomain);
            }
            
            // Rename directory back if it was renamed
            if ($renamed && File::exists($newPath)) {
                \Log::info("Renaming directory back: $newPath -> $oldPath");
                $this->executeSudoCommand("mv {$newPath} {$oldPath}");
            }
            
            // Recreate old Nginx config if it was deleted
            if ($oldConfigDeleted && File::exists($oldPath)) {
                \Log::info("Recreating old Nginx config for: $oldDomain");
                $this->createNginxConfig($oldDomain);
            }
            
            // Try to reload nginx
            try {
                $this->executeSudoCommand("nginx -t && systemctl reload nginx");
            } catch (\Exception $e) {
                \Log::warning("Failed to reload nginx during rollback: " . $e->getMessage());
            }
            
            \Log::info("Rollback completed for domain update");
        } catch (\Exception $e) {
            \Log::error("Rollback failed for domain update: " . $e->getMessage());
        }
    }
}