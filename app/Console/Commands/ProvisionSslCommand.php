<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use App\Models\ActivityLog;
use App\Services\NotificationService;
use App\Http\Controllers\DomainController;

class ProvisionSslCommand extends Command
{
    protected $signature = 'ssl:provision {domain} {--action=install} {--www} {--force}';
    protected $description = 'Provision or renew an SSL certificate in the background';

    private string $domain;
    private string $domainSafe;
    private string $statusFile;
    private string $logFile;

    public function handle(): int
    {
        $this->domain = strtolower(trim($this->argument('domain')));
        $action = strtolower($this->option('action') ?: 'install');
        $includeWww = (bool) $this->option('www');
        $force = (bool) $this->option('force');

        $this->domainSafe = preg_replace('/[^a-z0-9_.-]/i', '_', $this->domain);
        $this->statusFile = storage_path("logs/ssl_{$this->domainSafe}_status.json");
        $this->logFile = storage_path("logs/ssl_{$this->domainSafe}.log");

        $this->updateStatus('running', 'Initializing SSL provisioning environment...', "Starting {$action} for {$this->domain} at " . now()->toDateTimeString() . "\n");

        try {
            $certbotPath = $this->getCertbotPath();
            if (!$certbotPath) {
                $this->appendLog("Certbot binary not found. Attempting auto-installation via apt-get...\n");
                $this->updateStatus('running', 'Installing Certbot package...');
                exec('sudo apt-get update 2>&1 && sudo apt-get install -y certbot python3-certbot-nginx 2>&1', $aptOut, $aptCode);
                $certbotPath = $this->getCertbotPath();
                if (!$certbotPath) {
                    throw new \Exception("Certbot is not installed and automatic installation failed.");
                }
            }

            $this->updateStatus('running', 'Repairing Nginx domain configurations and cleaning checkpoints...');
            $this->appendLog("Ensuring managed domain directories exist...\n");
            try {
                app(DomainController::class)->repairManagedDomainStructures();
            } catch (\Throwable $e) {
                $this->appendLog("Notice: repairManagedDomainStructures: " . $e->getMessage() . "\n");
            }

            exec('sudo rm -rf /var/lib/letsencrypt/temp_checkpoint 2>/dev/null');

            $output = [];
            $returnCode = 0;

            if ($action === 'renew') {
                $this->updateStatus('running', "Requesting certificate renewal for {$this->domain}...");
                $cmd = "sudo {$certbotPath} --nginx -d " . escapeshellarg($this->domain);
                if ($includeWww) {
                    $cmd .= " -d " . escapeshellarg("www.{$this->domain}");
                }
                $cmd .= " --force-renewal --non-interactive --agree-tos --register-unsafely-without-email 2>&1";
            } else {
                $this->updateStatus('running', "Requesting Let's Encrypt certificate for {$this->domain}...");
                $cmd = "sudo {$certbotPath} --nginx -d " . escapeshellarg($this->domain);
                if ($includeWww) {
                    $cmd .= " -d " . escapeshellarg("www.{$this->domain}");
                }
                $cmd .= " --non-interactive --agree-tos --register-unsafely-without-email 2>&1";
            }

            $this->appendLog("Running: {$cmd}\n");
            exec($cmd, $output, $returnCode);
            exec("sudo sed -i '/le_http_01_cert_challenge.conf/d' /etc/nginx/nginx.conf 2>/dev/null");

            $outputStr = implode("\n", $output);
            $this->appendLog($outputStr . "\n");

            // Handle edge case where certbot issued the cert but hit checkpoint revert conflict
            if ($returnCode !== 0 && (strpos($outputStr, 'overwrite challenge file') !== false || strpos($outputStr, 'Unable to revert temporary config') !== false)) {
                $this->appendLog("Attempting fallback deploy for existing certificate...\n");
                exec('sudo rm -rf /var/lib/letsencrypt/temp_checkpoint 2>/dev/null');
                $fallbackOut = [];
                $fallbackCode = 0;
                exec("sudo {$certbotPath} install --cert-name " . escapeshellarg($this->domain) . " --nginx --non-interactive 2>&1", $fallbackOut, $fallbackCode);
                $fallbackStr = implode("\n", $fallbackOut);
                $this->appendLog($fallbackStr . "\n");

                if ($fallbackCode === 0) {
                    $returnCode = 0;
                    $outputStr .= "\n" . $fallbackStr;
                }
            }

            if ($returnCode !== 0) {
                $errorMsg = 'Failed to install SSL certificate';
                if (strpos($outputStr, 'too many certificates') !== false) {
                    $errorMsg = 'Let\'s Encrypt rate limit reached. Please wait before requesting another certificate.';
                } elseif (strpos($outputStr, 'DNS problem') !== false || strpos($outputStr, 'Could not reach') !== false) {
                    $errorMsg = 'DNS verification failed. Ensure your domain points to this server IP address.';
                } elseif (strpos($outputStr, 'No module named') !== false || strpos($outputStr, 'not found') !== false) {
                    $errorMsg = 'Certbot plugin error. Try reinstalling python3-certbot-nginx.';
                }

                $this->updateStatus('failed', 'Installation failed', null, $errorMsg, $outputStr);

                NotificationService::send(
                    "SSL Certificate {$action} Failed",
                    "<p>An attempt to {$action} an SSL certificate for <strong>{$this->domain}</strong> has failed.</p><pre>" . e($outputStr) . "</pre>"
                );

                Log::error("SSL {$action} failed for {$this->domain}: {$errorMsg}");
                return 1;
            }

            // Success: reload nginx and clear caches
            $this->updateStatus('running', 'Reloading Nginx and caching certificates...');
            exec("sudo systemctl reload nginx 2>&1", $reloadOut);
            $this->appendLog(implode("\n", $reloadOut) . "\n");

            cache()->forget("ssl_info_{$this->domain}");
            cache()->forget("dns_active_{$this->domain}");

            ActivityLog::log(
                $action === 'renew' ? 'RENEW_SSL' : 'INSTALL_SSL',
                'SSL',
                "SSL certificate successfully {$action}ed for {$this->domain}"
            );

            NotificationService::send(
                "SSL Certificate " . ucfirst($action) . "ed Successfully",
                "<p>An SSL certificate has been successfully {$action}ed for domain: <strong>{$this->domain}</strong>.</p><p>Time: " . now()->toDateTimeString() . "</p>"
            );

            $this->updateStatus('completed', 'Certificate installed and active', null, null, $outputStr);
            $this->appendLog("SSL {$action} completed successfully for {$this->domain}.\n");

            return 0;

        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            $this->appendLog("Fatal exception: {$errorMsg}\n");
            $this->updateStatus('failed', 'Fatal error during SSL installation', null, $errorMsg, $errorMsg);

            Log::error("ProvisionSslCommand exception for {$this->domain}: " . $errorMsg);
            return 1;
        }
    }

    private function getCertbotPath(): ?string
    {
        $paths = [
            '/usr/bin/certbot',
            '/snap/bin/certbot',
            '/usr/local/bin/certbot',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        $output = [];
        $returnCode = 0;
        exec('which certbot 2>/dev/null', $output, $returnCode);
        if ($returnCode === 0 && !empty($output[0])) {
            return trim($output[0]);
        }

        return null;
    }

    private function updateStatus(string $status, string $progress, ?string $initialLog = null, ?string $error = null, ?string $details = null): void
    {
        $existing = file_exists($this->statusFile) ? json_decode(@file_get_contents($this->statusFile), true) : [];
        if (!is_array($existing)) {
            $existing = [];
        }

        $data = [
            'status' => $status,
            'domain' => $this->domain,
            'progress' => $progress,
            'started_at' => $existing['started_at'] ?? now()->toDateTimeString(),
            'finished_at' => in_array($status, ['completed', 'failed']) ? now()->toDateTimeString() : null,
            'error' => $error,
            'details' => $details,
        ];

        File::put($this->statusFile, json_encode($data, JSON_PRETTY_PRINT));

        if ($initialLog !== null) {
            File::put($this->logFile, $initialLog);
        }
    }

    private function appendLog(string $message): void
    {
        File::append($this->logFile, $message);
    }
}
