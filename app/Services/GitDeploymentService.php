<?php

namespace App\Services;

use App\Models\GitDeployment;
use App\Models\DeploymentLog;
use App\Models\CommandBlacklist;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

class GitDeploymentService
{
    /**
     * Hardcoded shell injection patterns that cannot be disabled.
     * These prevent command chaining and injection in YAML commands.
     */
    private array $hardcodedBlocks = [
        '`',       // backtick execution
        '$(',      // subshell execution
        '#{',      // Ruby-style interpolation
    ];

    /**
     * Supported runtimes and how to check their version.
     */
    private array $runtimeChecks = [
        'php' => ['command' => 'php -v', 'regex' => '/PHP\s+([\d.]+)/'],
        'node' => ['command' => 'node -v', 'regex' => '/v?([\d.]+)/'],
        'python' => ['command' => 'python3 --version', 'regex' => '/Python\s+([\d.]+)/'],
        'ruby' => ['command' => 'ruby -v', 'regex' => '/ruby\s+([\d.]+)/'],
        'go' => ['command' => 'go version', 'regex' => '/go([\d.]+)/'],
        'java' => ['command' => 'java -version 2>&1', 'regex' => '/"([\d.]+)"/'],
    ];

    /**
     * Run the full deployment pipeline.
     */
    public function deploy(GitDeployment $deployment): bool
    {
        try {
            // Clear previous logs for this deployment
            $deployment->logs()->delete();

            // Step 1: Clone repository
            if (!$this->cloneRepository($deployment)) {
                return false;
            }

            // Step 2: Parse nimbus.yaml
            $yamlConfig = $this->parseYamlConfig($deployment);

            // Step 3: Check runtime versions
            if ($yamlConfig && isset($yamlConfig['runtime'])) {
                if (!$this->checkRuntimes($deployment, $yamlConfig['runtime'])) {
                    return false;
                }
            }

            // Step 4: Run install commands
            if ($yamlConfig && isset($yamlConfig['install'])) {
                if (!$this->runCommands($deployment, 'install', $yamlConfig['install'])) {
                    return false;
                }
            }

            // Step 5: Run build commands
            if ($yamlConfig && isset($yamlConfig['build'])) {
                if (!$this->runCommands($deployment, 'build', $yamlConfig['build'])) {
                    return false;
                }
            }

            // Step 6: Setup environment variables
            if ($yamlConfig && isset($yamlConfig['env'])) {
                $this->setupEnvVariables($deployment, $yamlConfig['env']);
            }

            // Step 7: Set proper permissions
            $this->setPermissions($deployment);

            // Step 8: Setup Supervisor (if needed)
            $this->setupSupervisor($deployment, $yamlConfig);

            // Step 9: Update Nginx if yaml has nginx config
            if ($yamlConfig && isset($yamlConfig['nginx'])) {
                $this->updateNginxConfig($deployment, $yamlConfig['nginx']);
            }

            // Mark as completed
            $deployment->update([
                'status' => 'completed',
                'last_deployed_at' => now(),
                'last_error' => null,
            ]);

            Log::info("Deployment completed successfully for {$deployment->domain}");
            return true;

        } catch (\Exception $e) {
            Log::error("Deployment failed for {$deployment->domain}: " . $e->getMessage());
            $deployment->update([
                'status' => 'failed',
                'last_error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Clone the repository to the domain's directory.
     */
    private function cloneRepository(GitDeployment $deployment): bool
    {
        $startTime = microtime(true);
        $domainPath = $deployment->getDomainPath();

        $log = $this->createLog($deployment, 'clone', 'running');

        try {
            $deployment->update(['status' => 'cloning']);

            // Build clone URL based on repo type
            $cloneUrl = $this->buildCloneUrl($deployment);

            // Clean existing content in the domain directory (keep logs dir)
            $cleanOutput = $this->executeCommand(
                "sudo find {$domainPath} -mindepth 1 -maxdepth 1 ! -name 'logs' -exec rm -rf {} +",
                $domainPath
            );

            // Clone the repository - use -c safe.directory='*' to bypass ownership checks
            $cloneCommand = "git -c safe.directory='*' clone {$cloneUrl} --branch {$deployment->branch} --single-branch --depth 1 {$domainPath}/repo_temp";
            $output = $this->executeCommand($cloneCommand);

            // Move contents from repo_temp to the domain root, preserving dotfiles without cp -a metadata issues.
            $this->executeCommand(
                "sudo bash -lc 'shopt -s dotglob nullglob && mv "
                . escapeshellarg($domainPath . "/repo_temp")
                . "/* "
                . escapeshellarg($domainPath)
                . "/ && rmdir "
                . escapeshellarg($domainPath . "/repo_temp")
                . "'"
            );

            // Add domain path to safe directories to avoid "dubious ownership" fatal errors when queuing as different user
            // We'll also use -c safe.directory='*' in commands below for extra safety
            $this->executeCommand("git config --global --add safe.directory " . escapeshellarg($domainPath));

            // Get current commit hash
            $commitHash = trim($this->executeCommand("cd {$domainPath} && git -c safe.directory='*' rev-parse HEAD")[0] ?? '');
            $deployment->update(['commit_hash' => $commitHash]);

            // Remove .git directory to save space (optional, keeps it cleaner)
            // We intentionally keep .git for potential future features like diff/rollback

            $duration = (int)(microtime(true) - $startTime);
            $log->update([
                'status' => 'success',
                'output' => "Repository cloned successfully.\nBranch: {$deployment->branch}\nCommit: {$commitHash}",
                'command' => "git clone [url] --branch {$deployment->branch}",
                'duration_seconds' => $duration,
            ]);

            return true;

        } catch (\Exception $e) {
            $duration = (int)(microtime(true) - $startTime);
            $log->update([
                'status' => 'failed',
                'output' => 'Clone failed: ' . $e->getMessage(),
                'duration_seconds' => $duration,
            ]);
            $deployment->update([
                'status' => 'failed',
                'last_error' => 'Repository clone failed: ' . $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Build the Git clone URL, injecting token for private HTTPS repos.
     */
    private function buildCloneUrl(GitDeployment $deployment): string
    {
        $url = $deployment->repo_url;

        if ($deployment->repo_type === 'private' && $deployment->url_type === 'https' && $deployment->access_token) {
            $token = $deployment->access_token;
            // Insert token into HTTPS URL: https://TOKEN@github.com/user/repo.git
            $url = preg_replace('/^https:\/\//', "https://{$token}@", $url);
        }

        return escapeshellarg($url);
    }

    /**
     * Parse the nimbus.yaml config file if it exists.
     */
    private function parseYamlConfig(GitDeployment $deployment): ?array
    {
        $startTime = microtime(true);
        $domainPath = $deployment->getDomainPath();
        $yamlPath = $domainPath . '/nimbus.yaml';
        $altYamlPath = $domainPath . '/nimbus.yml';

        $log = $this->createLog($deployment, 'yaml_parse', 'running');

        try {
            // Check for nimbus.yaml or nimbus.yml
            $actualPath = null;
            if (file_exists($yamlPath)) {
                $actualPath = $yamlPath;
            } elseif (file_exists($altYamlPath)) {
                $actualPath = $altYamlPath;
            }

            if (!$actualPath) {
                $duration = (int)(microtime(true) - $startTime);
                $log->update([
                    'status' => 'skipped',
                    'output' => 'No nimbus.yaml or nimbus.yml found in repository root. Skipping automated setup.',
                    'duration_seconds' => $duration,
                ]);
                return null;
            }

            $yamlContent = file_get_contents($actualPath);
            $config = Yaml::parse($yamlContent);

            if (!is_array($config)) {
                throw new \Exception('Invalid YAML config: must be a valid YAML document');
            }

            // Validate version
            $version = $config['version'] ?? null;
            if ($version !== 1 && $version !== '1') {
                throw new \Exception("Unsupported nimbus.yaml version: {$version}. Only version 1 is supported.");
            }

            // Save parsed config
            $deployment->update([
                'yaml_path' => $actualPath,
                'yaml_config' => $config,
            ]);

            $duration = (int)(microtime(true) - $startTime);
            $log->update([
                'status' => 'success',
                'output' => "nimbus.yaml parsed successfully.\n" . $this->summarizeYamlConfig($config),
                'duration_seconds' => $duration,
            ]);

            return $config;

        } catch (ParseException $e) {
            $duration = (int)(microtime(true) - $startTime);
            $log->update([
                'status' => 'failed',
                'output' => 'YAML parse error: ' . $e->getMessage(),
                'duration_seconds' => $duration,
            ]);
            $deployment->update([
                'status' => 'failed',
                'last_error' => 'Failed to parse nimbus.yaml: ' . $e->getMessage(),
            ]);
            return null;
        } catch (\Exception $e) {
            $duration = (int)(microtime(true) - $startTime);
            $log->update([
                'status' => 'failed',
                'output' => 'Config validation error: ' . $e->getMessage(),
                'duration_seconds' => $duration,
            ]);
            return null;
        }
    }

    /**
     * Check if required runtime versions are satisfied.
     */
    private function checkRuntimes(GitDeployment $deployment, array $runtimes): bool
    {
        $startTime = microtime(true);
        $log = $this->createLog($deployment, 'runtime_check', 'running');
        $results = [];
        $allPassed = true;

        foreach ($runtimes as $runtime => $requiredVersion) {
            if (!isset($this->runtimeChecks[$runtime])) {
                $results[] = "⚠ Unknown runtime: {$runtime} (skipped)";
                continue;
            }

            $check = $this->runtimeChecks[$runtime];

            try {
                $output = $this->executeCommand($check['command']);
                $outputStr = implode("\n", $output);

                if (preg_match($check['regex'], $outputStr, $matches)) {
                    $installedVersion = $matches[1];

                    if (version_compare($installedVersion, $requiredVersion, '>=')) {
                        $results[] = "✓ {$runtime}: {$installedVersion} (required: {$requiredVersion})";
                    } else {
                        $results[] = "✗ {$runtime}: {$installedVersion} installed, but {$requiredVersion}+ required";
                        $allPassed = false;
                    }
                } else {
                    $results[] = "✗ {$runtime}: installed but version could not be determined";
                    $allPassed = false;
                }
            } catch (\Exception $e) {
                $results[] = "✗ {$runtime}: not installed (required: {$requiredVersion})";
                $allPassed = false;
            }
        }

        $duration = (int)(microtime(true) - $startTime);
        $outputText = implode("\n", $results);

        if ($allPassed) {
            $log->update([
                'status' => 'success',
                'output' => "All runtime requirements satisfied:\n{$outputText}",
                'duration_seconds' => $duration,
            ]);
        } else {
            $log->update([
                'status' => 'failed',
                'output' => "Runtime requirements not met:\n{$outputText}",
                'duration_seconds' => $duration,
            ]);
            $deployment->update([
                'status' => 'failed',
                'last_error' => "Runtime requirements not met. Check deployment logs for details.",
            ]);
        }

        return $allPassed;
    }

    /**
     * Run a set of commands (install or build phase).
     */
    private function runCommands(GitDeployment $deployment, string $phase, array $commands): bool
    {
        $domainPath = $deployment->getDomainPath();
        $deployment->update(['status' => $phase === 'install' ? 'installing' : 'building']);

        foreach ($commands as $index => $command) {
            $startTime = microtime(true);
            $log = $this->createLog($deployment, $phase, 'running', $command);

            // Security check: scan command against blacklist
            $blacklistResult = $this->scanCommand($command);
            if ($blacklistResult !== null) {
                $duration = (int)(microtime(true) - $startTime);
                $log->update([
                    'status' => 'failed',
                    'output' => "⛔ BLOCKED: Command matched security blacklist.\nPattern: {$blacklistResult}\nCommand: {$command}",
                    'duration_seconds' => $duration,
                ]);
                $deployment->update([
                    'status' => 'failed',
                    'last_error' => "Command blocked by security policy: {$command}",
                ]);
                Log::warning("Blacklisted command blocked during deployment of {$deployment->domain}: {$command}");
                return false;
            }

            try {
                $output = $this->executeCommand($command, $domainPath);
                $outputStr = implode("\n", $output);
                $duration = (int)(microtime(true) - $startTime);

                $log->update([
                    'status' => 'success',
                    'output' => $outputStr ?: 'Command completed successfully.',
                    'duration_seconds' => $duration,
                ]);
            } catch (\Exception $e) {
                $duration = (int)(microtime(true) - $startTime);
                $log->update([
                    'status' => 'failed',
                    'output' => 'Command failed: ' . $e->getMessage(),
                    'duration_seconds' => $duration,
                ]);
                $deployment->update([
                    'status' => 'failed',
                    'last_error' => "Command failed: {$command} — " . $e->getMessage(),
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * Scan a command against the blacklist.
     * Returns the matched pattern or null if safe.
     */
    public function scanCommand(string $command): ?string
    {
        // Layer 1: Hardcoded blocks (cannot be disabled)
        foreach ($this->hardcodedBlocks as $pattern) {
            if (str_contains($command, $pattern)) {
                return "Hardcoded security block: contains '{$pattern}'";
            }
        }

        // Layer 2: Check for shell chaining operators (;, &&, ||)
        // These are checked separately because they're common injection vectors
        // Users should use separate lines in nimbus.yaml instead of chaining
        if (str_contains($command, ';')) {
            return "Shell chaining operator ';' detected. Use separate lines in nimbus.yaml instead.";
        }
        if (str_contains($command, '&&')) {
            return "Shell chaining operator '&&' detected. Use separate lines in nimbus.yaml instead.";
        }
        if (str_contains($command, '||')) {
            return "Shell chaining operator '||' detected. Use separate lines in nimbus.yaml instead.";
        }

        // Layer 3: Database blacklist patterns
        $blacklistEntries = CommandBlacklist::where('is_active', true)->get();

        foreach ($blacklistEntries as $entry) {
            if ($entry->matches($command)) {
                return "Blacklist rule: [{$entry->type}] {$entry->pattern} — {$entry->description}";
            }
        }

        return null;
    }

    /**
     * Setup environment variables from YAML config.
     */
    private function setupEnvVariables(GitDeployment $deployment, array $envVars): void
    {
        $startTime = microtime(true);
        $domainPath = $deployment->getDomainPath();
        $envFile = $domainPath . '/.env';
        $log = $this->createLog($deployment, 'env_setup', 'running');

        try {
            $envContent = '';
            if (file_exists($envFile)) {
                $envContent = file_get_contents($envFile);
            }

            foreach ($envVars as $key => $value) {
                // Sanitize key
                $key = preg_replace('/[^A-Za-z0-9_]/', '', $key);
                if (empty($key)) continue;

                $value = (string) $value;

                // Check if key already exists in .env
                if (preg_match("/^{$key}=.*/m", $envContent)) {
                    // Update existing value
                    $envContent = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $envContent);
                } else {
                    // Append new value
                    $envContent .= "\n{$key}={$value}";
                }
            }

            // Write back using sudo to handle permissions
            $tempFile = "/tmp/nimbus_env_" . $deployment->id . "_" . time();
            file_put_contents($tempFile, trim($envContent) . "\n");
            $this->executeCommand("sudo mv {$tempFile} {$envFile}");
            $this->executeCommand("sudo chown www-data:www-data {$envFile}");
            $this->executeCommand("sudo chmod 640 {$envFile}");

            $duration = (int)(microtime(true) - $startTime);
            $log->update([
                'status' => 'success',
                'output' => "Environment variables set: " . implode(', ', array_keys($envVars)),
                'duration_seconds' => $duration,
            ]);
        } catch (\Exception $e) {
            $duration = (int)(microtime(true) - $startTime);
            $log->update([
                'status' => 'failed',
                'output' => 'Failed to set environment variables: ' . $e->getMessage(),
                'duration_seconds' => $duration,
            ]);
        }
    }

    /**
     * Setup Supervisor to keep the app running in the background.
     */
    private function setupSupervisor(GitDeployment $deployment, ?array $yamlConfig): void
    {
        if (!$yamlConfig) return;

        $domainPath = $deployment->getDomainPath();
        $domain = $deployment->domain;
        
        // Determine the start command
        $startCommand = null;
        if (isset($yamlConfig['start']) && is_string($yamlConfig['start'])) {
            $startCommand = $yamlConfig['start'];
        } elseif (isset($yamlConfig['runtime']['node'])) {
            $startCommand = "npm start"; // Default for Node.js apps
        }

        if (!$startCommand) {
            return; // No start command specified and not a Node app
        }

        // Use bash -c to ensure path resolution and environment handling works correctly in Supervisor
        $fullCommand = "/bin/bash -c " . escapeshellarg($startCommand);

        $startTime = microtime(true);
        $log = $this->createLog($deployment, 'supervisor_setup', 'running');

        try {
            // Ensure logs directory exists - Supervisor will fail to spawn if the log path is invalid
            $this->executeCommand("sudo mkdir -p {$domainPath}/logs");
            $this->executeCommand("sudo chown www-data:www-data {$domainPath}/logs");

            // Generate safe program name
            $programName = "nimbus_app_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $domain);
            $confPath = "/etc/supervisor/conf.d/{$programName}.conf";

            $supervisorConf = "[program:{$programName}]\n";
            $supervisorConf .= "process_name=%(program_name)s_%(process_num)02d\n";
            $supervisorConf .= "command={$fullCommand}\n";
            $supervisorConf .= "directory={$domainPath}\n";
            $supervisorConf .= "autostart=true\n";
            $supervisorConf .= "autorestart=true\n";
            $supervisorConf .= "user=www-data\n";
            $supervisorConf .= "numprocs=1\n";
            $supervisorConf .= "redirect_stderr=true\n";
            $supervisorConf .= "stdout_logfile={$domainPath}/logs/supervisor.log\n";
            
            // Read environment variables to pass to Supervisor
            $envString = "";
            $envFile = "{$domainPath}/.env";
            if (file_exists($envFile)) {
                $lines = explode("\n", file_get_contents($envFile));
                $envs = [
                    'PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"'
                ];
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line) || str_starts_with($line, '#')) continue;
                    $parts = explode('=', $line, 2);
                    if (count($parts) === 2) {
                        $key = trim($parts[0]);
                        $val = trim($parts[1]);
                        // Remove surrounding quotes if they exist in the .env file
                        $val = trim($val, "'\"");
                        // Supervisor environment values must have commas escaped with \,
                        $val = str_replace(',', '\,', $val);
                        $envs[] = "{$key}=\"{$val}\"";
                    }
                }
                if (!empty($envs)) {
                    // Filter out environment variables with commas to prevent Supervisor syntax errors
                    // or handle them carefully. For now, let's just join with commas.
                    $supervisorConf .= "environment=" . implode(",", $envs) . "\n";
                }
            }

            // Write config using sudo
            $tempFile = "/tmp/supervisor_conf_" . time();
            file_put_contents($tempFile, $supervisorConf);
            $this->executeCommand("sudo mv {$tempFile} {$confPath}");
            $this->executeCommand("sudo chown root:root {$confPath}");
            
            // Apply changes
            $this->executeCommand("sudo supervisorctl reread");
            $this->executeCommand("sudo supervisorctl update");
            
            // Restart the app
            $this->executeCommand("sudo supervisorctl restart {$programName}:*");

            $duration = (int)(microtime(true) - $startTime);
            $log->update([
                'status' => 'success',
                'output' => "Supervisor configured for {$domain}.\nProgram name: {$programName}\nCommand: {$startCommand}",
                'duration_seconds' => $duration,
            ]);
        } catch (\Exception $e) {
            $duration = (int)(microtime(true) - $startTime);
            $log->update([
                'status' => 'failed',
                'output' => 'Failed to setup Supervisor: ' . $e->getMessage(),
                'duration_seconds' => $duration,
            ]);
        }
    }

    /**
     * Set proper file permissions on the deployed project.
     */
    private function setPermissions(GitDeployment $deployment): void
    {
        $startTime = microtime(true);
        $domainPath = $deployment->getDomainPath();
        $log = $this->createLog($deployment, 'permissions', 'running');

        try {
            $this->executeCommand("sudo chown -R www-data:www-data {$domainPath}");
            $this->executeCommand("sudo find {$domainPath} -type d -exec chmod 2775 {} \\;");
            $this->executeCommand("sudo find {$domainPath} -type f -exec chmod 664 {} \\;");

            // Make common directories writable if they exist
            $writableDirs = ['storage', 'bootstrap/cache', 'var', 'tmp', 'cache', 'writable'];
            foreach ($writableDirs as $dir) {
                if (is_dir("{$domainPath}/{$dir}")) {
                    $this->executeCommand("sudo chmod -R 775 {$domainPath}/{$dir}");
                }
            }

            $duration = (int)(microtime(true) - $startTime);
            $log->update([
                'status' => 'success',
                'output' => "Permissions set successfully.\nOwner: www-data:www-data\nDirs: 755, Files: 644\nWritable dirs: " . implode(', ', $writableDirs),
                'duration_seconds' => $duration,
            ]);
        } catch (\Exception $e) {
            $duration = (int)(microtime(true) - $startTime);
            $log->update([
                'status' => 'failed',
                'output' => 'Failed to set permissions: ' . $e->getMessage(),
                'duration_seconds' => $duration,
            ]);
        }
    }

    /**
     * Update Nginx configuration based on YAML nginx section.
     */
    private function updateNginxConfig(GitDeployment $deployment, array $nginxConfig): void
    {
        $startTime = microtime(true);
        $log = $this->createLog($deployment, 'nginx_update', 'running');

        try {
            $domain = $deployment->domain;
            $domainPath = $deployment->getDomainPath();
            $root = $nginxConfig['root'] ?? 'public';
            $phpVersion = $nginxConfig['php_version'] ?? '8.2';
            $fullRoot = "{$domainPath}/{$root}";

            $configContent = $this->generateNginxConfig($domain, $fullRoot, $domainPath, $phpVersion);

            $tempFile = "/tmp/nginx_{$domain}_" . time() . ".conf";
            file_put_contents($tempFile, $configContent);

            $configPath = "/etc/nginx/sites-available/{$domain}";
            $this->executeCommand("sudo mv {$tempFile} {$configPath}");
            $this->executeCommand("sudo chmod 644 {$configPath}");

            // Ensure symlink exists
            $symlinkPath = "/etc/nginx/sites-enabled/{$domain}";
            if (!file_exists($symlinkPath)) {
                $this->executeCommand("sudo ln -s {$configPath} {$symlinkPath}");
            }

            // Ensure logs directory exists before testing Nginx to prevent emerg failures
            $this->executeCommand("sudo mkdir -p {$domainPath}/logs");
            $this->executeCommand("sudo touch {$domainPath}/logs/access.log {$domainPath}/logs/error.log");
            $this->executeCommand("sudo chown -R www-data:www-data {$domainPath}/logs");

            // Test and reload nginx
            $this->executeCommand("sudo nginx -t");
            $this->executeCommand("sudo systemctl reload nginx");

            $duration = (int)(microtime(true) - $startTime);
            $log->update([
                'status' => 'success',
                'output' => "Nginx config updated.\nDocument root: {$fullRoot}\nPHP version: {$phpVersion}",
                'duration_seconds' => $duration,
            ]);
        } catch (\Exception $e) {
            $duration = (int)(microtime(true) - $startTime);
            $log->update([
                'status' => 'failed',
                'output' => 'Nginx config update failed: ' . $e->getMessage(),
                'duration_seconds' => $duration,
            ]);
        }
    }

    /**
     * Generate Nginx config content for a domain.
     */
    private function generateNginxConfig(string $domain, string $root, string $domainPath, string $phpVersion): string
    {
        return <<<NGINX
server {
    listen 80;
    listen [::]:80;
    
    server_name {$domain} www.{$domain};
    root {$root};
    
    index index.php index.html index.htm;
    
    # Logs
    access_log {$domainPath}/logs/access.log;
    error_log {$domainPath}/logs/error.log;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Upload limit
    client_max_body_size 2048M;
    
    # PHP handling
    location ~ \.php\$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)\$;
        fastcgi_pass unix:/var/run/php/php{$phpVersion}-fpm.sock;
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
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)\$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
    
    # Try files
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
}
NGINX;
    }

    /**
     * Validate a repository URL and check connectivity.
     */
    public function validateRepository(string $url, string $type, ?string $token, string $urlType): array
    {
        try {
            $checkUrl = $url;
            if ($type === 'private' && $urlType === 'https' && $token) {
                $checkUrl = preg_replace('/^https:\/\//', "https://{$token}@", $url);
            }

            $escapedUrl = escapeshellarg($checkUrl);
            $output = [];
            $returnCode = 0;
            exec("export HOME=/tmp && git -c safe.directory='*' ls-remote {$escapedUrl} HEAD 2>&1", $output, $returnCode);

            if ($returnCode === 0) {
                return ['valid' => true, 'message' => 'Repository is accessible'];
            } else {
                $error = implode("\n", $output);
                return ['valid' => false, 'message' => "Cannot access repository: {$error}"];
            }
        } catch (\Exception $e) {
            return ['valid' => false, 'message' => 'Validation error: ' . $e->getMessage()];
        }
    }

    /**
     * Fetch available branches from a repository.
     */
    public function fetchBranches(string $url, string $type, ?string $token, string $urlType): array
    {
        try {
            $checkUrl = $url;
            if ($type === 'private' && $urlType === 'https' && $token) {
                $checkUrl = preg_replace('/^https:\/\//', "https://{$token}@", $url);
            }

            $escapedUrl = escapeshellarg($checkUrl);
            $output = [];
            $returnCode = 0;
            exec("export HOME=/tmp && git -c safe.directory='*' ls-remote --heads {$escapedUrl} 2>&1", $output, $returnCode);

            if ($returnCode !== 0) {
                return ['success' => false, 'branches' => [], 'error' => implode("\n", $output)];
            }

            $branches = [];
            foreach ($output as $line) {
                if (preg_match('/refs\/heads\/(.+)$/', $line, $matches)) {
                    $branches[] = $matches[1];
                }
            }

            sort($branches);

            return ['success' => true, 'branches' => $branches, 'error' => null];
        } catch (\Exception $e) {
            return ['success' => false, 'branches' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Execute a shell command and return output.
     */
    private function executeCommand(string $command, ?string $cwd = null): array
    {
        $output = [];
        $returnCode = 0;

        // Ensure HOME is set so Git doesn't fail with "fatal: $HOME not set"
        // We use /tmp as it is always writable and safe for temporary git config access
        $fullCommand = "export HOME=/tmp && " . $command;
        if ($cwd) {
            $fullCommand = "export HOME=/tmp && cd {$cwd} && {$command}";
        }

        Log::debug("Deployment command: {$fullCommand}");
        exec("{$fullCommand} 2>&1", $output, $returnCode);

        if ($returnCode !== 0) {
            $outputStr = implode("\n", $output);
            throw new \Exception("Command exited with code {$returnCode}: {$outputStr}");
        }

        return $output;
    }

    /**
     * Create a deployment log entry.
     */
    private function createLog(GitDeployment $deployment, string $step, string $status, ?string $command = null): DeploymentLog
    {
        return DeploymentLog::create([
            'git_deployment_id' => $deployment->id,
            'step' => $step,
            'status' => $status,
            'command' => $command,
        ]);
    }

    /**
     * Summarize YAML config for log output.
     */
    private function summarizeYamlConfig(array $config): string
    {
        $lines = ["Version: " . ($config['version'] ?? 'unknown')];

        if (isset($config['runtime'])) {
            $runtimes = [];
            foreach ($config['runtime'] as $rt => $ver) {
                $runtimes[] = "{$rt} {$ver}+";
            }
            $lines[] = "Runtimes: " . implode(', ', $runtimes);
        }

        if (isset($config['install'])) {
            $lines[] = "Install Steps: " . count($config['install']);
        }

        if (isset($config['build'])) {
            $lines[] = "Build Steps: " . count($config['build']);
        }

        if (isset($config['env'])) {
            $lines[] = "Env Variables: " . count($config['env']);
        }

        if (isset($config['nginx'])) {
            $lines[] = "Nginx Override: yes";
        }

        return implode("\n", $lines);
    }
}
