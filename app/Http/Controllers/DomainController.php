<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

class DomainController extends Controller
{
    private $basePath = '/var/www/';

    /**
     * Return all domain folders
     */
    public function index()
    {
        try {
            if (!File::exists($this->basePath)) {
                return response()->json([
                    'error' => "Base path {$this->basePath} does not exist on this system."
                ], 500);
            }

            $directories = collect(File::directories($this->basePath))
                ->map(function ($path) {
                    return basename($path);
                })
                ->filter(function ($name) {
                    // Ignore system directories and the Nimbus control panel itself
                    return !in_array(strtolower($name), [
                        'html', 
                        'default', 
                        'public', 
                        'cgi-bin',
                        'nimbus'  // Exclude Nimbus control panel
                    ]);
                })
                ->values();

            return response()->json($directories);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load domains: ' . $e->getMessage()
            ], 500);
        }
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
                    'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/i'
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

            $domain = strtolower(trim($request->domain));
            $path = $this->basePath . $domain;

            // Check if domain already exists
            if (File::exists($path)) {
                return response()->json([
                    'error' => 'Domain already exists'
                ], 409);
            }

            // Create folder structure using sudo for proper permissions
            $this->executeSudoCommand("mkdir -p {$path}/public {$path}/logs");
            $this->executeSudoCommand("chown -R www-data:www-data {$path}");
            $this->executeSudoCommand("chmod -R 755 {$path}");
            $createdDirs = true;

            // Create basic index file
            $indexContent = $this->getDefaultIndexContent($domain);
            file_put_contents("$path/public/index.php", $indexContent);

            // Create .env file placeholder
            file_put_contents("$path/.env", "APP_ENV=production\nAPP_DEBUG=false\n");

            // Create Nginx configuration
            $this->createNginxConfig($domain);
            $createdNginx = true;

            // Test Nginx configuration before reloading
            $this->executeSudoCommand("nginx -t");
            
            // Reload Nginx
            $this->executeSudoCommand("systemctl reload nginx");

            // Log the creation
            \Log::info("Domain created: $domain by user " . auth()->id());

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
                    'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/i'
                ]
            ], [
                'domain.required' => 'Domain name is required',
                'domain.regex' => 'Please enter a valid domain name (e.g., example.com)',
                'domain.max' => 'Domain name is too long (max 253 characters)'
            ]);

            $oldPath = $this->basePath . $oldDomain;
            $newDomain = strtolower(trim($request->domain));
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
     * Delete domain folder
     */
    public function destroy($domain)
    {
        try {
            \Log::info("Starting deletion process for domain: $domain");
            
            // Sanitize domain name to prevent command injection
            $domain = strtolower(trim($domain));
            if (!preg_match('/^[a-z0-9.-]+$/', $domain)) {
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
            $symlinkPath = "/etc/nginx/sites-enabled/{$domain}";
            if (file_exists($symlinkPath)) {
                \Log::info("Removing Nginx symlink: $symlinkPath");
                $this->executeSudoCommand("rm -f {$symlinkPath}");
            }

            // Step 2: Delete Nginx config file
            $configPath = "/etc/nginx/sites-available/{$domain}";
            if (file_exists($configPath)) {
                \Log::info("Removing Nginx config: $configPath");
                $this->executeSudoCommand("rm -f {$configPath}");
            }

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
    <title>Welcome to $domain</title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .container {
            text-align: center;
            padding: 2rem;
        }
        h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Welcome to $domain</h1>
        <p>Your domain is ready! Start building something amazing.</p>
        <p style="font-size: 0.9rem; margin-top: 2rem;">Powered by Nimbus Control Panel</p>
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
    root {$domainPath}/public;
    
    index index.php index.html index.htm;
    
    # Logs
    access_log {$domainPath}/logs/access.log;
    error_log {$domainPath}/logs/error.log;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
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
        $configPath = "/etc/nginx/sites-available/{$domain}";
        $symlinkPath = "/etc/nginx/sites-enabled/{$domain}";

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