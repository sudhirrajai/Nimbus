<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LicenseService
{
    protected $apiBaseUrl;

    /**
     * RSA Public Key for verifying signed license tokens from VmCoreCentral.
     * This key is the ONLY way to verify tokens — users cannot forge valid signatures
     * without VmCoreCentral's private key.
     */
    private const PUBLIC_KEY = '-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA1Wi2SNgaaOkh6i6YaRjn
QRG3mTQeMVw0acUyNAlL2JJbsOEyTT635qVZAJIU4o6yKrGsEMgDxHKaQ4IpfGqq
WlDJVoDS8GHg5E/4GS12WjKmADHw+JYwLwlz8QrNUbo0KcWcP7xdqwKGVWzg9Ae0
9+vQ52ZW+w5Uq4QX+Q1bvmxbOmkjOJNroAzHTVgLnCcIjEaC13kLKk71xU9DuRGR
zdNOU1ZogTtjwdYsMGk6gQLTX+GT9x/pJQnot8rqe95H3AEhabbDS9guCr9RyI9K
kk0EZPCR8eYcYRXXPQrBdvYxxpfm5LzZr4s+NfwpeFezkZZCPUttDUjX9LfOpy5w
/wIDAQAB
-----END PUBLIC KEY-----';

    /** Offline grace period in hours before the panel degrades */
    private const GRACE_PERIOD_HOURS = 72;

    public function __construct()
    {
        $url = config('services.vmcore.api_url', 'http://localhost:8001');

        // Upgrade http:// to https:// for external domains to avoid POST->GET redirect issues
        if (str_starts_with($url, 'http://') && !str_contains($url, 'localhost') && !str_contains($url, '127.0.0.1')) {
            $url = 'https://' . substr($url, 7);
        }

        $this->apiBaseUrl = rtrim($url, '/') . '/api/v1';
    }

    /**
     * Check if the license is valid.
     *
     * @param  bool  $force  If true, bypass cache and make a fresh network call.
     * @return array{status: bool, message: string, plan?: string, max_domains?: int}
     */
    public function checkLicense(bool $force = false): array
    {
        // 1. Check persistent lock first (survives cache clears, code restarts)
        // Bypass this check if we are forcing a fresh check (to allow unlocking)
        if (!$force && $this->isLocked()) {
            $reason = $this->getSetting('license_lock_reason') ?? 'License verification failed.';
            return ['status' => false, 'message' => $reason];
        }

        $licenseKey = $this->getLicenseKey();
        if (!$licenseKey) {
            return ['status' => false, 'message' => 'No license key found.'];
        }

        // 2. If not forced, try to validate the locally stored signed token
        if (!$force) {
            $tokenResult = $this->validateStoredToken();
            if ($tokenResult !== null) {
                return $tokenResult;
            }
            // Token expired/missing — fall through to network call
        }

        // 3. Make network call to VmCoreCentral
        return $this->verifyWithServer($licenseKey);
    }

    /**
     * Validate the locally stored signed token without any network call.
     * Returns null if the token is expired/missing and a network call is needed.
     */
    private function validateStoredToken(): ?array
    {
        $storedToken = $this->getSetting('license_token');
        if (!$storedToken) {
            return null;
        }

        $decoded = $this->verifySignedToken($storedToken);
        if (!$decoded) {
            // Signature invalid — possible tampering
            Log::warning('License token signature verification failed — possible tampering');
            $this->triggerDegradation('Token signature verification failed');
            return ['status' => false, 'message' => 'License verification failed.'];
        }

        // Check if token's valid_until has expired
        $validUntil = strtotime($decoded['valid_until'] ?? '');
        if ($validUntil && $validUntil < time()) {
            // Token expired — need a fresh one from the server
            return null;
        }

        return [
            'status'      => true,
            'message'     => 'License is valid.',
            'plan'        => $decoded['plan'] ?? 'free',
            'max_domains' => $decoded['max_domains'] ?? 3,
        ];
    }

    /**
     * Contact VmCoreCentral to verify the license and get a signed token.
     */
    private function verifyWithServer(string $licenseKey): array
    {
        try {
            $rootUser = \App\Models\User::where('role', 'root')->first();

            $response = Http::timeout(10)->post($this->apiBaseUrl . '/verify', [
                'license_key' => $licenseKey,
                'server_ip'   => $this->getServerIp(),
                'machine_id'  => $this->getMachineId(),
                'domain'      => request()->getHost(),
                'admin_name'  => $rootUser ? $rootUser->name : null,
                'admin_email' => $rootUser ? $rootUser->email : null,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Store the signed token for offline validation
                if (!empty($data['signed_token'])) {
                    // Cryptographically verify the token before storing it to avoid redirect loops if keys mismatch
                    $decoded = $this->verifySignedToken($data['signed_token']);
                    if (!$decoded) {
                        Log::error('Signature verification failed for the license token received from VmCoreCentral.');
                        $this->triggerDegradation('License token signature verification failed (key mismatch).');
                        return ['status' => false, 'message' => 'License verification failed (signature verification mismatch). Please ensure the Nimbus public key matches VmCoreCentral\'s private key.'];
                    }

                    $this->setSetting('license_token', $data['signed_token']);
                }

                // Record successful check
                $this->setSetting('last_successful_license_check', now()->toIso8601String());

                if (!empty($data['status_changed_at'])) {
                    $this->setSetting('last_status_sync_at', $data['status_changed_at']);
                }

                // Clear any degradation state
                $this->clearDegradation();

                return [
                    'status'      => true,
                    'message'     => $data['message'] ?? 'License is valid.',
                    'plan'        => $data['plan'] ?? 'free',
                    'max_domains' => $data['max_domains'] ?? 3,
                ];
            }

            // Explicit rejection (403/404) — the license is invalid, no grace period
            if ($response->status() === 403 || $response->status() === 404) {
                $message = $response->json('message') ?? 'License verification failed.';
                $this->triggerDegradation($message);
                return ['status' => false, 'message' => $message];
            }

            // Other errors — treat as server issues, apply grace period
            return $this->handleOffline('Server returned status ' . $response->status());

        } catch (\Exception $e) {
            // Network error — VmCoreCentral unreachable. Apply grace period.
            Log::warning('License verification failed: ' . $e->getMessage());
            return $this->handleOffline($e->getMessage());
        }
    }

    /**
     * Handle the case when VmCoreCentral is unreachable.
     * Applies grace period logic — panel continues working for GRACE_PERIOD_HOURS.
     */
    private function handleOffline(string $reason): array
    {
        $lastCheck = $this->getSetting('last_successful_license_check');

        if ($lastCheck) {
            $lastCheckTime = strtotime($lastCheck);
            $hoursSince = (time() - $lastCheckTime) / 3600;

            if ($hoursSince <= self::GRACE_PERIOD_HOURS) {
                // Still within grace period — allow access using stored token
                $storedToken = $this->getSetting('license_token');
                if ($storedToken) {
                    $decoded = $this->verifySignedToken($storedToken);
                    if ($decoded) {
                        return [
                            'status'      => true,
                            'message'     => 'License validated offline (grace period).',
                            'plan'        => $decoded['plan'] ?? 'free',
                            'max_domains' => $decoded['max_domains'] ?? 3,
                        ];
                    }
                }

                // No valid token but within grace — still allow
                return [
                    'status'  => true,
                    'message' => 'License validated offline (grace period).',
                ];
            }

            // Grace period exceeded
            $this->triggerDegradation('Licensing server unreachable for over ' . self::GRACE_PERIOD_HOURS . ' hours.');
            return ['status' => false, 'message' => 'Could not connect to licensing server. Grace period exceeded.'];
        }

        // No previous successful check — cannot grant grace
        return ['status' => false, 'message' => 'Could not connect to licensing server.'];
    }

    /**
     * Verify an RSA-signed token using the hardcoded public key.
     *
     * @return array|null  Decoded payload if valid, null if invalid.
     */
    public function verifySignedToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }

        $payloadB64 = $parts[0];
        $signatureB64 = $parts[1];

        $payload = $this->base64urlDecode($payloadB64);
        $signature = $this->base64urlDecode($signatureB64);

        if (!$payload || !$signature) {
            return null;
        }

        $publicKey = openssl_pkey_get_public(self::PUBLIC_KEY);
        if (!$publicKey) {
            Log::error('Failed to load RSA public key for license verification');
            return null;
        }

        $verified = openssl_verify($payload, $signature, $publicKey, OPENSSL_ALGO_SHA256);

        if ($verified !== 1) {
            return null;
        }

        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Send a lightweight heartbeat ping to VmCoreCentral.
     */
    public function sendHeartbeat(): bool
    {
        $licenseKey = $this->getLicenseKey();
        if (!$licenseKey) {
            return false;
        }

        try {
            $response = Http::timeout(10)->post($this->apiBaseUrl . '/heartbeat', [
                'license_key' => $licenseKey,
                'machine_id'  => $this->getMachineId(),
                'server_ip'   => $this->getServerIp(),
                'version'     => $this->getPanelVersion(),
            ]);

            if ($response->status() === 403) {
                // License suspended/revoked via heartbeat — trigger degradation
                $this->triggerDegradation($response->json('message') ?? 'License suspended.');
                return false;
            }

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['license_status']) && $data['license_status'] !== 'active') {
                    $this->triggerDegradation($data['message'] ?? 'License is ' . $data['license_status'] . '.');
                    return false;
                }

                if (!empty($data['status_changed_at'])) {
                    $serverChangedAt = strtotime($data['status_changed_at']);
                    $localSyncedAt = strtotime($this->getSetting('last_status_sync_at') ?? '');

                    if (!$localSyncedAt || $serverChangedAt > $localSyncedAt) {
                        Log::info("License status change detected on server. Triggering force check...");
                        $this->checkLicense(force: true);
                    }
                }

                return true;
            }

            return false;
        } catch (\Exception $e) {
            // Network error — don't degrade on heartbeat failure, the hourly check handles grace
            Log::info('Heartbeat failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the decoded license token data (plan, max_domains, etc.)
     */
    public function getLicenseToken(): ?array
    {
        $storedToken = $this->getSetting('license_token');
        if (!$storedToken) {
            return null;
        }

        return $this->verifySignedToken($storedToken);
    }

    /**
     * Trigger silent degradation — sets flags that scattered checks will pick up.
     */
    private function triggerDegradation(string $reason): void
    {
        $this->setSetting('license_is_locked', '1');
        $this->setSetting('license_lock_reason', $reason);

        // Track when degradation first started (don't overwrite if already set)
        if (!$this->getSetting('license_degraded_at')) {
            $this->setSetting('license_degraded_at', now()->toIso8601String());
        }

        Log::warning('License degradation triggered: ' . $reason);
    }

    /**
     * Clear degradation state — called on successful verification.
     */
    private function clearDegradation(): void
    {
        DB::table('settings')->whereIn('key', [
            'license_is_locked',
            'license_lock_reason',
            'license_degraded_at',
        ])->delete();
    }

    /**
     * Check if the panel is in a locked/degraded state.
     */
    public function isLocked(): bool
    {
        try {
            return $this->getSetting('license_is_locked') === '1';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the stored license key.
     */
    public function getLicenseKey(): ?string
    {
        return DB::table('settings')->where('key', 'license_key')->value('value');
    }

    /**
     * Set the license key.
     */
    public function setLicenseKey(string $key): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'license_key'],
            ['value' => $key, 'updated_at' => now()]
        );
        Cache::forget('license_status');
    }

    /**
     * Generate or retrieve a unique machine ID.
     */
    public function getMachineId(): string
    {
        $path = storage_path('app/machine_id');

        if (file_exists($path)) {
            return trim(file_get_contents($path));
        }

        $id = hash('sha256', php_uname() . node_machine_id());
        file_put_contents($path, $id);

        return $id;
    }

    /**
     * Clear the cached license status.
     */
    public function clearCache(): void
    {
        Cache::forget('license_status');
    }

    /**
     * Clear the persistent lock — called during successful re-activation.
     */
    public function clearLock(): void
    {
        $this->clearDegradation();
        Cache::forget('license_status');
    }

    /**
     * Deactivate and remove the license key.
     */
    public function deactivate(): void
    {
        $licenseKey = $this->getLicenseKey();

        if ($licenseKey) {
            try {
                $deactivateUrl = $this->apiBaseUrl . '/deactivate';

                Http::timeout(10)->post($deactivateUrl, [
                    'license_key' => $licenseKey,
                    'machine_id'  => $this->getMachineId(),
                ]);
            } catch (\Exception $e) {
                // Ignore connection errors during deactivation
            }
        }

        // Clear all license-related settings
        DB::table('settings')->whereIn('key', [
            'license_key',
            'license_token',
            'last_successful_license_check',
            'license_is_locked',
            'license_lock_reason',
            'license_degraded_at',
        ])->delete();

        $this->clearCache();
    }

    /**
     * Get the current panel version.
     */
    private function getPanelVersion(): string
    {
        $versionFile = base_path('VERSION');
        if (file_exists($versionFile)) {
            return trim(file_get_contents($versionFile));
        }
        return '0.0.0';
    }

    // ─── Internal Helpers ────────────────────────────────────────────

    private function getServerIp(): string
    {
        return request()->server('SERVER_ADDR') ?? (gethostbyname(gethostname()) ?: '127.0.0.1');
    }

    private function getSetting(string $key): ?string
    {
        try {
            return DB::table('settings')->where('key', $key)->value('value');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function setSetting(string $key, string $value): void
    {
        try {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
        } catch (\Exception $e) {
            Log::error("Failed to set setting {$key}: " . $e->getMessage());
        }
    }

    private function base64urlDecode(string $data): string|false
    {
        $padded = str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT);
        return base64_decode($padded);
    }
}

/**
 * Helper to get a semi-unique machine ID on Linux/Windows
 */
function node_machine_id() {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $cmd = 'powershell -command "(Get-CimInstance Win32_ComputerSystemProduct).UUID"';
        $output = shell_exec($cmd);
        return trim($output);
    } else {
        // Linux
        if (file_exists('/etc/machine-id')) {
            return trim(file_get_contents('/etc/machine-id'));
        }
        return shell_exec('hostname');
    }
}
