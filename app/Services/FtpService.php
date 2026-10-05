<?php

namespace App\Services;

use App\Models\FtpAccount;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class FtpService
{
    /**
     * Check if pure-pw is available on the system.
     */
    public function isInstalled(): bool
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return true; // Mocked for local development
        }

        $check = @shell_exec('which pure-pw 2>/dev/null');
        return !empty(trim((string) $check));
    }

    /**
     * Check if pure-ftpd service is active.
     */
    public function isServiceRunning(): bool
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return true;
        }

        $status = @shell_exec('systemctl is-active pure-ftpd 2>/dev/null');
        return trim((string) $status) === 'active';
    }

    /**
     * Get Server IP or hostname for client configuration.
     */
    public function getServerHost(): string
    {
        // Try getting public IP or fallback to server hostname
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $ip = @shell_exec('curl -s -m 2 https://api.ipify.org 2>/dev/null');
            if (!empty(trim((string) $ip)) && filter_var(trim($ip), FILTER_VALIDATE_IP)) {
                return trim($ip);
            }
        }

        return request()->getHost() ?: '127.0.0.1';
    }

    /**
     * Get connection summary info.
     */
    public function getConnectionInfo(): array
    {
        return [
            'host' => $this->getServerHost(),
            'port' => 21,
            'passive_ports' => '40000 - 40100',
            'protocol' => 'FTP / FTPS (Explicit TLS over port 21)',
            'is_running' => $this->isServiceRunning(),
            'is_installed' => $this->isInstalled(),
        ];
    }

    /**
     * List FTP accounts with authorization scope applied.
     */
    public function listAccounts(User $user, ?string $domain = null)
    {
        $query = FtpAccount::with('user:id,name,email')->latest();

        if ($domain) {
            $query->where('domain', $domain);
        }

        // Role-based visibility
        if (!$user->isRoot() && $user->role !== 'admin') {
            $allowedDomains = $user->assigned_domains ?? [];
            $query->whereIn('domain', $allowedDomains);
        }

        return $query->get();
    }

    /**
     * Create a new virtual FTP account.
     */
    public function createAccount(User $user, array $data): FtpAccount
    {
        $domain = trim($data['domain']);
        $username = trim($data['username']);
        $password = $data['password'];
        $homedir = rtrim(trim($data['homedir']), '/');
        $quotaMb = !empty($data['quota_mb']) ? (int) $data['quota_mb'] : null;
        $notes = !empty($data['notes']) ? trim($data['notes']) : null;

        // 1. Validation
        if (!preg_match('/^[a-z0-9_\-\.]+$/i', $username)) {
            throw new \InvalidArgumentException('Username may only contain letters, numbers, underscores, dashes, and periods.');
        }

        if (FtpAccount::where('username', $username)->exists()) {
            throw new \InvalidArgumentException("FTP account '{$username}' already exists.");
        }

        if (strlen($password) < 6) {
            throw new \InvalidArgumentException('Password must be at least 6 characters.');
        }

        // Path safety: must be inside /var/www
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $normalizedPath = realpath($homedir) ?: $homedir;
            if (!str_starts_with($normalizedPath, '/var/www')) {
                throw new \InvalidArgumentException('Directory must reside within /var/www.');
            }

            // Create directory if not exists
            if (!is_dir($homedir)) {
                @mkdir($homedir, 0755, true);
            }
        }

        // 2. Resolve UID / GID
        $uid = 33; // www-data
        $gid = 33; // www-data

        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $siteUser = SiteIsolationService::siteUser($domain);
            $userUid = @shell_exec("id -u " . escapeshellarg($siteUser) . " 2>/dev/null");
            $userGid = @shell_exec("id -g " . escapeshellarg($siteUser) . " 2>/dev/null");

            if (is_numeric(trim((string) $userUid)) && is_numeric(trim((string) $userGid))) {
                $uid = (int) trim($userUid);
                $gid = (int) trim($userGid);
            }
        }

        // 3. Execute pure-pw command on server
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $cmd = sprintf(
                "printf '%%s\\n%%s\\n' %s %s | pure-pw useradd %s -u %d -g %d -d %s %s -m",
                escapeshellarg($password),
                escapeshellarg($password),
                escapeshellarg($username),
                $uid,
                $gid,
                escapeshellarg($homedir),
                $quotaMb ? "-N {$quotaMb}" : ""
            );

            $output = [];
            $returnCode = 0;
            @exec($cmd, $output, $returnCode);

            if ($returnCode !== 0) {
                $errorMsg = implode("\n", $output);
                Log::error("pure-pw useradd failed for {$username}: {$errorMsg}");
                throw new \RuntimeException("Failed to create system FTP user: {$errorMsg}");
            }
        }

        // 4. Record in database
        $account = FtpAccount::create([
            'user_id' => $user->id,
            'domain' => $domain,
            'username' => $username,
            'homedir' => $homedir,
            'quota_mb' => $quotaMb,
            'is_active' => true,
            'notes' => $notes,
        ]);

        if (class_exists(ActivityLog::class)) {
            ActivityLog::log('Created FTP account', "Created FTP account {$username} for {$domain}", 'ftp');
        }

        return $account;
    }

    /**
     * Update password for an FTP account.
     */
    public function updatePassword(FtpAccount $account, string $newPassword): void
    {
        if (strlen($newPassword) < 6) {
            throw new \InvalidArgumentException('Password must be at least 6 characters.');
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $cmd = sprintf(
                "printf '%%s\\n%%s\\n' %s %s | pure-pw passwd %s -m",
                escapeshellarg($newPassword),
                escapeshellarg($newPassword),
                escapeshellarg($account->username)
            );

            $output = [];
            $returnCode = 0;
            @exec($cmd, $output, $returnCode);

            if ($returnCode !== 0) {
                $errorMsg = implode("\n", $output);
                Log::error("pure-pw passwd failed for {$account->username}: {$errorMsg}");
                throw new \RuntimeException("Failed to update FTP password: {$errorMsg}");
            }
        }

        $account->touch();

        if (class_exists(ActivityLog::class)) {
            ActivityLog::log('Updated FTP password', "Changed password for FTP account {$account->username}", 'ftp');
        }
    }

    /**
     * Update disk quota.
     */
    public function updateQuota(FtpAccount $account, ?int $quotaMb): void
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $quotaFlag = $quotaMb && $quotaMb > 0 ? "-N {$quotaMb}" : "-N ''";
            $cmd = sprintf(
                "pure-pw usermod %s %s -m",
                escapeshellarg($account->username),
                $quotaFlag
            );

            @exec($cmd);
        }

        $account->update(['quota_mb' => $quotaMb]);
    }

    /**
     * Toggle active/suspended status.
     */
    public function toggleStatus(FtpAccount $account): bool
    {
        $newStatus = !$account->is_active;

        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            // When inactive, deny all IPs (-R 0.0.0.0/0). When active, remove denial (-R '')
            $flag = $newStatus ? "-R ''" : "-R 0.0.0.0/0";
            $cmd = sprintf(
                "pure-pw usermod %s %s -m",
                escapeshellarg($account->username),
                $flag
            );

            @exec($cmd);
        }

        $account->update(['is_active' => $newStatus]);

        if (class_exists(ActivityLog::class)) {
            $action = $newStatus ? 'Resumed' : 'Suspended';
            ActivityLog::log("{$action} FTP account", "{$action} FTP account {$account->username}", 'ftp');
        }

        return $newStatus;
    }

    /**
     * Delete an FTP account.
     */
    public function deleteAccount(FtpAccount $account): void
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $cmd = sprintf("pure-pw userdel %s -m", escapeshellarg($account->username));
            @exec($cmd);
        }

        $username = $account->username;
        $domain = $account->domain;
        $account->delete();

        if (class_exists(ActivityLog::class)) {
            ActivityLog::log('Deleted FTP account', "Deleted FTP account {$username} for {$domain}", 'ftp');
        }
    }
}
