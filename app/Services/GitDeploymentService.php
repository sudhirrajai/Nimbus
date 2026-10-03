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
    public function deploy(GitDeployment $deployment, array $envOverrides = []): bool
    {
        $domainPath = $deployment->getDomainPath();
        $siteUser = SiteIsolationService::siteUser($deployment->domain);

        try {
            // Step 0: Ensure site user exists and grant deployment write access to $domainPath
            SiteIsolationService::ensureIsolatedUser($deployment->domain, $domainPath);
            SiteIsolationService::executeSudo("mkdir -p " . escapeshellarg($domainPath));
            SiteIsolationService::executeSudo("chown -R {$siteUser}:{$siteUser} " . escapeshellarg($domainPath));
            SiteIsolationService::executeSudo("chmod 775 " . escapeshellarg($domainPath));
            SiteIsolationService::executeSudo("setfacl -R -m u:www-data:rwx " . escapeshellarg($domainPath));
            SiteIsolationService::executeSudo("setfacl -R -d -m u:www-data:rwx " . escapeshellarg($domainPath));

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

            // Step 4: Setup environment variables (YAML env + saved runtime_env + interactive overrides)
            $this->setupEnvVariables($deployment, $yamlConfig['env'] ?? [], $envOverrides);

            // Step 5: Run install commands
            if ($yamlConfig && isset($yamlConfig['install'])) {
                if (!$this->runCommands($deployment, 'install', $yamlConfig['install'])) {
                    return false;
                }
            }

            // Step 6: Run build commands
            if ($yamlConfig && isset($yamlConfig['build'])) {
                if (!$this->runCommands($deployment, 'build', $yamlConfig['build'])) {
                    return false;
                }
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
        } finally {
            // ALWAYS re-apply strict tenant isolation permissions so the site is never left exposed
            try {
                SiteIsolationService::securePath($domainPath, $deployment->domain);
            } catch (\Exception $permEx) {
                Log::warning("Failed to secure path after deployment for {$deployment->domain}: " . $permEx->getMessage());
            }
        }
    }

    /**
     * Ensure the server SSH key and known_hosts file exist and have correct permissions.
     */
    public function ensureSshKeyExists(): string
    {
        $sshDir = '/var/www/.ssh';
        $keyPath = "{$sshDir}/id_ed25519";
        $knownHostsPath = "{$sshDir}/known_hosts";

        if (!file_exists($sshDir)) {
            @exec("sudo mkdir -p {$sshDir} 2>&1");
            @exec("sudo chown -R www-data:www-data {$sshDir} 2>&1");
            @exec("sudo chmod 700 {$sshDir} 2>&1");
        }

        if (!file_exists($keyPath)) {
            @exec("sudo -u www-data ssh-keygen -t ed25519 -f {$keyPath} -N '' -C 'nimbus-deploy@server' 2>&1");
            @exec("sudo chown www-data:www-data {$keyPath} {$keyPath}.pub 2>&1");
            @exec("sudo chmod 600 {$keyPath} 2>&1");
            @exec("sudo chmod 644 {$keyPath}.pub 2>&1");
        } else {
            @exec("sudo chmod 600 {$keyPath} 2>&1");
        }

        if (!file_exists($knownHostsPath)) {
            @exec("sudo -u www-data touch {$knownHostsPath} 2>&1");
            @exec("sudo chown www-data:www-data {$knownHostsPath} 2>&1");
            @exec("sudo chmod 644 {$knownHostsPath} 2>&1");
            @exec("sudo -u www-data ssh-keyscan -H github.com >> {$knownHostsPath} 2>&1");
            @exec("sudo -u www-data ssh-keyscan -H gitlab.com >> {$knownHostsPath} 2>&1");
            @exec("sudo -u www-data ssh-keyscan -H bitbucket.org >> {$knownHostsPath} 2>&1");
        }

        return $keyPath;
    }

    /**
     * Get environment prefix for Git commands (SSH config, non-interactive flags).
     */
    public function getGitEnv(): string
    {
        $sshDir = '/var/www/.ssh';
        $keyPath = "{$sshDir}/id_ed25519";
        $knownHosts = "{$sshDir}/known_hosts";

        $sshCmd = "ssh -o StrictHostKeyChecking=accept-new -o UserKnownHostsFile={$knownHosts}";
        if (file_exists($keyPath)) {
            $sshCmd .= " -i {$keyPath} -o IdentitiesOnly=yes";
        }

        return "export GIT_TERMINAL_PROMPT=0 GIT_SSH_COMMAND=" . escapeshellarg($sshCmd);
    }

    /**
     * Build the authenticated URL for private HTTPS repos or clean URL.
     */
    public function buildAuthenticatedUrl(string $url, ?string $token): string
    {
        $url = trim($url);
        if (empty($token)) {
            return $url;
        }

        $token = trim($token);
        $parsed = parse_url($url);

        if (!$parsed || !isset($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), ['http', 'https'])) {
            return $url;
        }

        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path = $parsed['path'] ?? '';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';

        // If token already includes username/prefix (e.g. "x-access-token:ghp_..." or "oauth2:glpat-...")
        if (str_contains($token, ':')) {
            $auth = $token;
        } elseif (str_contains(strtolower($host), 'gitlab')) {
            $auth = 'oauth2:' . rawurlencode($token);
        } elseif (str_contains(strtolower($host), 'bitbucket')) {
            $auth = 'x-token-auth:' . rawurlencode($token);
        } else {
            // GitHub and generic git providers
            $auth = 'x-access-token:' . rawurlencode($token);
        }

        return "https://{$auth}@{$host}{$port}{$path}{$query}{$fragment}";
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

            if ($deployment->url_type === 'ssh' || preg_match('/^(git@|ssh:\/\/)/', $deployment->repo_url)) {
                $this->ensureSshKeyExists();
            }

            // Build clone URL based on repo type
            $cloneUrl = $this->buildCloneUrl($deployment);

            // Ensure domainPath exists and www-data has write access via ACL before cleaning and cloning
            $siteUser = SiteIsolationService::siteUser($deployment->domain);
            SiteIsolationService::executeSudo("mkdir -p " . escapeshellarg($domainPath));
            SiteIsolationService::executeSudo("chmod 775 " . escapeshellarg($domainPath));
            SiteIsolationService::executeSudo("setfacl -m u:www-data:rwx " . escapeshellarg($domainPath));
            SiteIsolationService::executeSudo("setfacl -d -m u:www-data:rwx " . escapeshellarg($domainPath));

            // Clean existing content in the domain directory
            $cleanOutput = $this->executeCommand(
                "sudo find {$domainPath} -mindepth 1 -maxdepth 1 -exec rm -rf {} +",
                $domainPath
            );

            // Re-apply write ACL after find/rm clean
            SiteIsolationService::executeSudo("setfacl -m u:www-data:rwx " . escapeshellarg($domainPath));
            SiteIsolationService::executeSudo("setfacl -d -m u:www-data:rwx " . escapeshellarg($domainPath));

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

            // Ensure site ownership and group write permissions for build steps
            SiteIsolationService::executeSudo("chown -R {$siteUser}:{$siteUser} " . escapeshellarg($domainPath));
            SiteIsolationService::executeSudo("chmod -R 775 " . escapeshellarg($domainPath));
            SiteIsolationService::executeSudo("setfacl -R -m u:www-data:rwx " . escapeshellarg($domainPath));
            SiteIsolationService::executeSudo("setfacl -R -d -m u:www-data:rwx " . escapeshellarg($domainPath));

            // Get current commit hash (bypassing ownership with -c safe.directory='*')
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
            $url = $this->buildAuthenticatedUrl($url, $deployment->access_token);
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
        // Allow harmless error-ignoring suffixes like '|| true', '|| false', '|| exit 0', '|| :'
        $sanitizedForPipe = preg_replace('/\s*\|\|\s*(true|false|:|exit\s+[0-9]+)\s*$/i', '', trim($command));
        if (str_contains($sanitizedForPipe, '||')) {
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
     * Setup environment variables from YAML config, saved runtime env, and interactive overrides.
     */
    private function setupEnvVariables(GitDeployment $deployment, array $yamlEnv = [], array $runtimeOverrides = []): void
    {
        $startTime = microtime(true);
        $domainPath = $deployment->getDomainPath();
        $envFile = $domainPath . '/.env';
        $log = $this->createLog($deployment, 'env_setup', 'running');

        try {
            $envContent = '';
            if (file_exists($envFile)) {
                $envContent = SiteIsolationService::readFile($envFile) ?? '';
            } elseif (file_exists($domainPath . '/.env.example')) {
                $envContent = SiteIsolationService::readFile($domainPath . '/.env.example') ?? '';
            }

            // Combine all sources in priority order:
            // 1. YAML env
            // 2. Saved runtime_env on deployment
            // 3. runtimeOverrides passed interactively
            $savedRuntimeEnv = is_array($deployment->runtime_env) ? $deployment->runtime_env : [];
            $mergedEnv = array_merge($yamlEnv, $savedRuntimeEnv, $runtimeOverrides);

            $configuredKeys = [];
            foreach ($mergedEnv as $key => $value) {
                // Sanitize key
                $key = preg_replace('/[^A-Za-z0-9_]/', '', (string)$key);
                if (empty($key)) continue;

                $valueStr = (string) $value;
                // If it's an unfulfilled prompt marker, don't overwrite
                if (preg_match('/^(prompt\(\)|<prompt>|\$\{.*\}|CHANGE_ME)$/i', trim($valueStr))) {
                    continue;
                }

                // Properly format value: if it has spaces, quotes, or special chars, quote it
                if (preg_match('/[\s#\'"$]/', $valueStr) && !preg_match('/^".*"$/', $valueStr)) {
                    $formattedValue = '"' . addcslashes($valueStr, '"$\\') . '"';
                } else {
                    $formattedValue = $valueStr;
                }

                // Check if key already exists in .env (either uncommented or commented)
                if (preg_match("/^#?\s*{$key}=.*/m", $envContent)) {
                    // Update existing value (and uncomment if was commented)
                    $envContent = preg_replace("/^#?\s*{$key}=.*/m", "{$key}={$formattedValue}", $envContent);
                } else {
                    // Append new value
                    $envContent .= "\n{$key}={$formattedValue}";
                }
                $configuredKeys[] = $key;
            }

            // Write back using sudo to handle permissions
            $tempFile = "/tmp/nimbus_env_" . $deployment->id . "_" . time();
            file_put_contents($tempFile, trim($envContent) . "\n");
            $this->executeCommand("sudo mv " . escapeshellarg($tempFile) . " " . escapeshellarg($envFile));
            $siteUser = SiteIsolationService::siteUser($deployment->domain);
            $this->executeCommand("sudo chown {$siteUser}:{$siteUser} " . escapeshellarg($envFile));
            $this->executeCommand("sudo chmod 660 " . escapeshellarg($envFile));
            $this->executeCommand("sudo setfacl -m u:www-data:rw " . escapeshellarg($envFile));

            $duration = (int)(microtime(true) - $startTime);
            $log->update([
                'status' => 'success',
                'output' => "Environment variables configured successfully.\nVariables set: " . (empty($configuredKeys) ? 'none' : implode(', ', $configuredKeys)),
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
     * Get detectable environment variables and sensitive credentials for interactive seeking.
     */
    public function getDetectableEnvVars(GitDeployment $deployment): array
    {
        $domainPath = $deployment->getDomainPath();
        $vars = [];

        // Helper to check sensitivity
        $isSensitive = function (string $key): bool {
            return (bool) preg_match('/(PASS|SECRET|KEY|TOKEN|CREDENTIAL|AUTH|HASH|PRIVATE|SALT)/i', $key);
        };

        // 1. Common default keys that web applications use (APP_KEY is omitted as it is generated via artisan key:generate)
        $defaultKeys = [
            'APP_URL' => ['label' => 'Application URL', 'sensitive' => false, 'placeholder' => 'https://' . $deployment->domain],
            'DB_CONNECTION' => ['label' => 'Database Connection', 'sensitive' => false, 'placeholder' => 'mysql, pgsql, sqlite'],
            'DB_HOST' => ['label' => 'Database Host', 'sensitive' => false, 'placeholder' => '127.0.0.1 or localhost'],
            'DB_PORT' => ['label' => 'Database Port', 'sensitive' => false, 'placeholder' => '3306 or 5432'],
            'DB_DATABASE' => ['label' => 'Database Name', 'sensitive' => false, 'placeholder' => 'database_name'],
            'DB_USERNAME' => ['label' => 'Database User', 'sensitive' => false, 'placeholder' => 'database_user'],
            'DB_PASSWORD' => ['label' => 'Database Password', 'sensitive' => true, 'placeholder' => 'Enter database password'],
        ];

        // 2. Parse from .env or .env.example in domain directory if available
        $envFiles = [$domainPath . '/.env', $domainPath . '/.env.example'];
        foreach ($envFiles as $file) {
            if (file_exists($file)) {
                $content = @file_get_contents($file);
                if ($content) {
                    $lines = explode("\n", $content);
                    foreach ($lines as $line) {
                        $line = trim($line);
                        // Matches KEY=VALUE or # KEY=VALUE
                        if (preg_match('/^#?\s*([A-Za-z0-9_]+)=(.*)$/', $line, $matches)) {
                            $k = $matches[1];
                            $v = trim($matches[2], " \t\n\r\0\x0B\"'");
                            // Skip APP_KEY since Laravel's key:generate sets it automatically
                            if ($k === 'APP_KEY') continue;
                            if (!isset($vars[$k])) {
                                $vars[$k] = [
                                    'key' => $k,
                                    'value' => $v,
                                    'sensitive' => $isSensitive($k),
                                    'source' => basename($file),
                                    'required' => false,
                                    'prompted' => (bool) preg_match('/^(prompt\(\)|<prompt>|\$\{.*\}|CHANGE_ME|TODO)$/i', $v),
                                ];
                            }
                        }
                    }
                }
            }
        }

        // 3. Parse from nimbus.yaml / nimbus.yml
        $yamlConfig = $deployment->yaml_config;
        if (!$yamlConfig) {
            $yamlPath = file_exists($domainPath . '/nimbus.yaml') ? $domainPath . '/nimbus.yaml' : (file_exists($domainPath . '/nimbus.yml') ? $domainPath . '/nimbus.yml' : null);
            if ($yamlPath) {
                try {
                    $yamlConfig = Yaml::parse(file_get_contents($yamlPath));
                } catch (\Exception $e) {}
            }
        }

        if (isset($yamlConfig['env']) && is_array($yamlConfig['env'])) {
            foreach ($yamlConfig['env'] as $k => $v) {
                $k = preg_replace('/[^A-Za-z0-9_]/', '', (string)$k);
                if (empty($k)) continue;
                $vStr = (string)$v;
                $isPrompt = (bool) preg_match('/^(prompt\(\)|<prompt>|\$\{.*\}|CHANGE_ME)$/i', trim($vStr));
                $vars[$k] = [
                    'key' => $k,
                    'value' => $isPrompt ? '' : $vStr,
                    'sensitive' => $isSensitive($k),
                    'source' => 'nimbus.yaml',
                    'required' => false,
                    'prompted' => $isPrompt,
                ];
            }
        }

        // 4. Merge saved runtime_env
        if (is_array($deployment->runtime_env)) {
            foreach ($deployment->runtime_env as $k => $v) {
                if (isset($vars[$k])) {
                    $vars[$k]['value'] = (string)$v;
                    $vars[$k]['saved'] = true;
                } else {
                    $vars[$k] = [
                        'key' => $k,
                        'value' => (string)$v,
                        'sensitive' => $isSensitive($k),
                        'source' => 'saved',
                        'required' => false,
                        'prompted' => false,
                        'saved' => true,
                    ];
                }
            }
        }

        // 5. If nothing detected from files/yaml (e.g. repo not cloned yet), supply default set
        if (empty($vars)) {
            foreach ($defaultKeys as $k => $info) {
                $vars[$k] = [
                    'key' => $k,
                    'value' => $k === 'APP_URL' ? ('https://' . $deployment->domain) : ($k === 'DB_CONNECTION' ? 'mysql' : ($k === 'DB_HOST' ? '127.0.0.1' : ($k === 'DB_PORT' ? '3306' : ''))),
                    'sensitive' => $info['sensitive'],
                    'source' => 'default',
                    'required' => false,
                    'prompted' => false,
                ];
            }
        }

        // Sort: prompted first, then sensitive, then alphabetical
        $result = array_values($vars);
        usort($result, function ($a, $b) {
            $scoreA = (!empty($a['prompted']) ? 3 : 0) + ($a['sensitive'] ? 1 : 0);
            $scoreB = (!empty($b['prompted']) ? 3 : 0) + ($b['sensitive'] ? 1 : 0);
            if ($scoreA !== $scoreB) {
                return $scoreB <=> $scoreA;
            }
            return strcmp($a['key'], $b['key']);
        });

        return $result;
    }

    /**
     * Setup Supervisor to keep the app running in the background.
     */
    private function setupSupervisor(GitDeployment $deployment, ?array $yamlConfig): void
    {
        if (!$yamlConfig) return;

        $domainPath = $deployment->getDomainPath();
        $domain = $deployment->domain;
        
        // Check if the user specified a custom supervisor configuration block
        if (isset($yamlConfig['supervisor']) && is_array($yamlConfig['supervisor'])) {
            $supConfig = $yamlConfig['supervisor'];
            $programName = $supConfig['program'] ?? null;
            $rawConf = $supConfig['config'] ?? null;
            
            if (!$programName || !$rawConf) {
                // If it's incomplete, log it and return
                $log = $this->createLog($deployment, 'supervisor_setup', 'failed');
                $log->update([
                    'output' => 'Failed to setup Supervisor: "supervisor" block must contain both "program" and "config" keys.',
                    'duration_seconds' => 0
                ]);
                return;
            }
            
            // Clean program name to prevent path injection
            $programName = preg_replace('/[^a-zA-Z0-9_]/', '_', $programName);
            
            // Replace templates {domainPath} and {domain} with actual values
            $rawConf = str_replace('{domainPath}', $domainPath, $rawConf);
            $rawConf = str_replace('{domain}', $domain, $rawConf);
            
            $startTime = microtime(true);
            $log = $this->createLog($deployment, 'supervisor_setup', 'running');
            
            try {
                // Ensure logs directory exists - Supervisor will fail to spawn if the log path is invalid
                $this->executeCommand("sudo mkdir -p {$domainPath}/logs");
                $this->executeCommand("sudo chown www-data:www-data {$domainPath}/logs");
                
                $confPath = "/etc/supervisor/conf.d/{$programName}.conf";
                
                // Write config using sudo
                $tempFile = "/tmp/supervisor_conf_" . time();
                file_put_contents($tempFile, $rawConf);
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
                    'output' => "Supervisor configured successfully using custom nimbus.yaml configuration.\nProgram name: {$programName}",
                    'duration_seconds' => $duration,
                ]);
            } catch (\Exception $e) {
                $duration = (int)(microtime(true) - $startTime);
                $log->update([
                    'status' => 'failed',
                    'output' => 'Failed to setup custom Supervisor: ' . $e->getMessage(),
                    'duration_seconds' => $duration,
                ]);
            }
            return;
        }

        // If no supervisor block is defined in nimbus.yaml, skip Supervisor setup entirely!
        return;
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
            \App\Services\SiteIsolationService::securePath($domainPath, $deployment->domain);

            // Make additional common custom directories writable if they exist
            $extraWritableDirs = ['var', 'tmp', 'cache', 'writable'];
            foreach ($extraWritableDirs as $dir) {
                if (is_dir("{$domainPath}/{$dir}")) {
                    $this->executeCommand("sudo chmod -R 775 {$domainPath}/{$dir}");
                }
            }

            $duration = (int)(microtime(true) - $startTime);
            $log->update([
                'status' => 'success',
                'output' => "Permissions set successfully via SiteIsolationService.\nOwner: " . \App\Services\SiteIsolationService::siteUser($deployment->domain) . "\nDirs: 750, Files: 640, Executables: +x",
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
        $sockPath = \App\Services\SiteIsolationService::socketPath($domain, $phpVersion);

        return <<<NGINX
server {
    listen 80;
    listen [::]:80;
    
    server_name {$domain} www.{$domain};
    root {$root};
    
    index index.php index.html index.htm;
    
    # Logs
    access_log /var/log/nginx/{$domain}.access.log;
    error_log /var/log/nginx/{$domain}.error.log;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Upload limit
    client_max_body_size 2048M;
    
    # PHP handling
    location ~ \.php\$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)\$;
        fastcgi_pass unix:{$sockPath};
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
        fastcgi_read_timeout 600;
        fastcgi_send_timeout 600;
        fastcgi_connect_timeout 600;
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
            $url = trim($url);
            if ($urlType === 'ssh' || preg_match('/^(git@|ssh:\/\/)/', $url)) {
                $this->ensureSshKeyExists();
            }

            $checkUrl = $url;
            if ($type === 'private' && $urlType === 'https' && $token) {
                $checkUrl = $this->buildAuthenticatedUrl($url, $token);
            }

            $escapedUrl = escapeshellarg($checkUrl);
            $gitEnv = $this->getGitEnv();
            $output = [];
            $returnCode = 0;
            exec("{$gitEnv} && export HOME=/tmp && git -c safe.directory='*' ls-remote {$escapedUrl} HEAD 2>&1", $output, $returnCode);

            if ($returnCode === 0) {
                return ['valid' => true, 'message' => 'Repository is accessible'];
            } else {
                $error = implode("\n", $output);
                if ($token) {
                    $error = str_replace($token, '***', $error);
                }
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
            $url = trim($url);
            if ($urlType === 'ssh' || preg_match('/^(git@|ssh:\/\/)/', $url)) {
                $this->ensureSshKeyExists();
            }

            $checkUrl = $url;
            if ($type === 'private' && $urlType === 'https' && $token) {
                $checkUrl = $this->buildAuthenticatedUrl($url, $token);
            }

            $escapedUrl = escapeshellarg($checkUrl);
            $gitEnv = $this->getGitEnv();
            $output = [];
            $returnCode = 0;
            exec("{$gitEnv} && export HOME=/tmp && git -c safe.directory='*' ls-remote --heads {$escapedUrl} 2>&1", $output, $returnCode);

            if ($returnCode !== 0) {
                $error = implode("\n", $output);
                if ($token) {
                    $error = str_replace($token, '***', $error);
                }
                return ['success' => false, 'branches' => [], 'error' => $error];
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

        // Prevent parent environment variables (like panel DB credentials) from leaking into child commands.
        // This forces the child command (like php artisan migrate) to read from its own local .env file.
        $unsets = "unset DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD APP_KEY APP_ENV APP_DEBUG APP_URL MAIL_HOST MAIL_PORT MAIL_USERNAME MAIL_PASSWORD MAIL_ENCRYPTION MAIL_FROM_ADDRESS LOG_CHANNEL SESSION_DRIVER CACHE_STORE QUEUE_CONNECTION";

        $gitEnv = $this->getGitEnv();

        $fullCommand = "{$unsets} && {$gitEnv} && export HOME=/tmp && " . $command;
        if ($cwd) {
            $fullCommand = "{$unsets} && {$gitEnv} && export HOME=/tmp && cd {$cwd} && {$command}";
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
