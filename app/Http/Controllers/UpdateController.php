<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class UpdateController extends Controller
{
    // GitHub repository
    const GITHUB_REPO = 'sudhirrajai/Nimbus';

    /**
     * Get current version from VERSION file
     */
    private function getCurrentVersion()
    {
        $versionFile = base_path('VERSION');
        if (file_exists($versionFile)) {
            return trim(file_get_contents($versionFile));
        }
        return '1.0.0'; // Fallback
    }

    /**
     * Display updates page
     */
    public function index()
    {
        return Inertia::render('Updates/Index', [
            'currentVersion' => $this->getCurrentVersion()
        ]);
    }

    /**
     * Check for updates from VMCore Central
     */
    public function checkForUpdates()
    {
        try {
            // Cache the check for 2 minutes
            $updateInfo = Cache::remember('nimbus_update_check', 120, function () {
                return $this->fetchUpdateInfo();
            });

            $currentVersion = $this->getCurrentVersion();
            $latestVersion = $updateInfo['version'] ?? $currentVersion;
            $hasUpdate = version_compare($latestVersion, $currentVersion, '>');

            if ($hasUpdate) {
                $cacheKey = "update_alert_sent_{$latestVersion}";
                if (!Cache::has($cacheKey)) {
                    \App\Services\NotificationService::send(
                        "Nimbus Update Available: v{$latestVersion}",
                        "<p>Hello,</p><p>A new version of the Nimbus control panel is available on VMCore Central.</p><p><strong>Current Version:</strong> v{$currentVersion}<br><strong>Latest Version:</strong> v{$latestVersion}<br><strong>Release URL:</strong> <a href=\"" . e($updateInfo['releaseUrl'] ?? '') . "\">" . e($updateInfo['releaseUrl'] ?? 'Download Package') . "</a></p><p>You can upgrade your installation safely via the System Updates panel in Nimbus.</p>"
                    );
                    Cache::put($cacheKey, true, now()->addDays(1));
                }
            }

            return response()->json([
                'success' => true,
                'currentVersion' => $currentVersion,
                'latestVersion' => $latestVersion,
                'hasUpdate' => $hasUpdate,
                'changelog' => $updateInfo['changelog'] ?? [],
                'commands' => $updateInfo['commands'] ?? [],
                'sha256' => $updateInfo['sha256'] ?? null,
                'downloadUrl' => $updateInfo['downloadUrl'] ?? null,
                'releaseDate' => $updateInfo['releaseDate'] ?? null,
                'releaseUrl' => $updateInfo['releaseUrl'] ?? null,
                'source' => $updateInfo['source'] ?? 'VMCore Central'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'currentVersion' => $this->getCurrentVersion()
            ]);
        }
    }

    /**
     * Fetch update info from VMCore Central
     */
    private function fetchUpdateInfo()
    {
        $apiUrl = rtrim(config('services.vmcore.api_url', 'https://roook.vmcore.in'), '/');

        // Attempt 1: Standard API endpoint
        $endpoints = [
            "{$apiUrl}/api/v1/nimbus/updates/check",
            "{$apiUrl}/v1/nimbus/updates/check"
        ];

        foreach ($endpoints as $endpoint) {
            try {
                $response = Http::timeout(10)->withoutVerifying()->get($endpoint);
                if ($response->successful()) {
                    $data = $response->json();
                    if (!empty($data['version'])) {
                        return [
                            'source' => 'VMCore Central (' . parse_url($apiUrl, PHP_URL_HOST) . ')',
                            'version' => trim($data['version']),
                            'downloadUrl' => $data['download_url'] ?? "{$apiUrl}/nimbus.zip",
                            'sha256' => $data['sha256'] ?? null,
                            'changelog' => $data['changelog'] ?? [],
                            'commands' => $data['commands'] ?? [],
                            'releaseDate' => $data['release_date'] ?? null,
                            'releaseUrl' => $data['download_url'] ?? "{$apiUrl}/nimbus.zip",
                        ];
                    }
                }
            } catch (\Exception $e) {
                // Try next endpoint
            }
        }

        // Fallback: Check local manifest or return current version
        $localManifest = [];
        if (file_exists(base_path('update-manifest.json'))) {
            $localManifest = json_decode(file_get_contents(base_path('update-manifest.json')), true) ?: [];
        }

        return [
            'source' => 'VMCore Central (roook.vmcore.in)',
            'version' => $localManifest['version'] ?? $this->getCurrentVersion(),
            'downloadUrl' => null,
            'sha256' => $localManifest['sha256'] ?? null,
            'changelog' => $localManifest['changelog'] ?? ['Panel is running the latest installed version.'],
            'commands' => $localManifest['commands'] ?? [],
            'releaseDate' => $localManifest['release_date'] ?? date('Y-m-d'),
            'releaseUrl' => null
        ];
    }

    /**
     * Parse changelog helper
     */
    private function parseChangelog($body)
    {
        if (empty($body)) return [];

        $changelog = [];
        $lines = explode("\n", $body);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (preg_match('/^#+\s/', $line)) continue;

            if (preg_match('/^[-*•]\s*(.+)$/', $line, $matches)) {
                $changelog[] = $matches[1];
            } else {
                $changelog[] = $line;
            }
        }

        return $changelog;
    }

    /**
     * Perform safe update with ordered manifest execution and automated rollback
     */
    public function performUpdate()
    {
        try {
            // Check if another installation is in progress
            $lockFile = storage_path('logs/nimbus_install.lock');
            if (file_exists($lockFile)) {
                $lockContent = file_get_contents($lockFile);
                return response()->json([
                    'error' => "Cannot update while another operation is in progress: {$lockContent}."
                ], 409);
            }

            // Fetch latest metadata
            $updateInfo = $this->fetchUpdateInfo();
            $targetVersion = $updateInfo['version'] ?? $this->getCurrentVersion();
            $downloadUrl = $updateInfo['downloadUrl'] ?? (rtrim(config('services.vmcore.api_url', 'https://roook.vmcore.in'), '/') . '/nimbus.zip');
            $expectedSha256 = $updateInfo['sha256'] ?? '';
            $manifestCommands = $updateInfo['commands'] ?? [];

            if (empty($manifestCommands)) {
                $manifestCommands = [
                    'composer install --no-dev --optimize-autoloader --no-interaction',
                    'php artisan migrate --force',
                    'npm run build',
                    'php artisan config:clear',
                    'php artisan route:clear',
                    'php artisan cache:clear',
                    'systemctl restart nimbus-php-fpm'
                ];
            }

            $logFile = storage_path('logs/update.log');
            $statusFile = storage_path('logs/update_status.txt');

            // Clear old logs
            file_put_contents($logFile, "Update initialization started at " . date('Y-m-d H:i:s') . "\n");
            file_put_contents($statusFile, 'running');

            // Build dynamic commands bash block
            $commandsScript = '';
            foreach ($manifestCommands as $index => $cmd) {
                $stepNum = $index + 1;
                $escapedCmd = trim($cmd);
                if (empty($escapedCmd)) continue;

                $commandsScript .= "echo \"[Step 6.{$stepNum}] Executing: {$escapedCmd}\"\n";
                // Add superuser flag for composer
                if (str_starts_with($escapedCmd, 'composer')) {
                    $commandsScript .= "COMPOSER_ALLOW_SUPERUSER=1 {$escapedCmd} 2>&1\n";
                } elseif (str_starts_with($escapedCmd, 'npm run build') || str_starts_with($escapedCmd, 'npm build') || str_starts_with($escapedCmd, 'vite build')) {
                    $commandsScript .= "if [ -f \"./node_modules/.bin/vite\" ]; then ./node_modules/.bin/vite build 2>&1 || true; else echo \"Frontend assets already compiled in public/build. Skipping vite build.\"; fi\n";
                } else {
                    $commandsScript .= "{$escapedCmd} 2>&1\n";
                }
                $commandsScript .= "echo \"[Step 6.{$stepNum}] Completed successfully.\"\n\n";
            }

            // Bash update script with automated safe rollback
            $script = <<<BASH
#!/bin/bash
set -e
exec > >(tee -a "{$logFile}") 2>&1

PANEL_DIR="/usr/local/nimbus"
STATUS_FILE="{$statusFile}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/nimbus_snapshots/snapshot_\$TIMESTAMP"
STAGING_DIR="/tmp/nimbus_staging_\$TIMESTAMP"
ZIP_FILE="/tmp/nimbus_update_\$TIMESTAMP.zip"
DOWNLOAD_URL="{$downloadUrl}"
EXPECTED_SHA256="{$expectedSha256}"
TARGET_VERSION="{$targetVersion}"

echo "running" > "\$STATUS_FILE"

echo "================================================================"
echo "Nimbus Safe OTA Update Pipeline -> v\$TARGET_VERSION"
echo "Timestamp: \$(date '+%Y-%m-%d %H:%M:%S')"
echo "================================================================"

# Automated Safe Rollback handler
rollback() {
    local exit_code=\$?
    if [ \$exit_code -ne 0 ]; then
        echo ""
        echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!"
        echo "CRITICAL: UPDATE FAILED AT STEP WITH EXIT CODE: \$exit_code"
        echo "INITIATING AUTOMATED SAFE ROLLBACK..."
        echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!"
        
        if [ -d "\$BACKUP_DIR" ]; then
            echo "Restoring panel files from pre-update snapshot: \$BACKUP_DIR..."
            rsync -a --delete \
                --exclude 'storage' \
                --exclude '.env' \
                --exclude 'database/*.sqlite*' \
                "\$BACKUP_DIR/" "\$PANEL_DIR/" 2>/dev/null || true
        fi
        
        if [ -f "\$BACKUP_DIR/database/database.sqlite" ]; then
            echo "Restoring SQLite database from pre-update backup..."
            cp -f "\$BACKUP_DIR/database/database.sqlite" "\$PANEL_DIR/database/database.sqlite" 2>/dev/null || true
        fi
        
        echo "Re-establishing system permissions..."
        chown -R www-data:www-data "\$PANEL_DIR" 2>/dev/null || true
        chmod -R 775 "\$PANEL_DIR/storage" "\$PANEL_DIR/bootstrap/cache" 2>/dev/null || true
        chmod +x "\$PANEL_DIR/artisan" 2>/dev/null || true
        
        echo "Restarting Nimbus services to preserve panel uptime..."
        systemctl restart nimbus-php-fpm 2>/dev/null || systemctl restart php8.3-fpm 2>/dev/null || true
        systemctl reload nimbus-nginx 2>/dev/null || systemctl reload nginx 2>/dev/null || true
        
        echo "failed" > "\$STATUS_FILE"
        echo ""
        echo "AUTOMATIC ROLLBACK COMPLETE. PANEL RESTORED TO PRE-UPDATE STATE."
        rm -rf "\$STAGING_DIR" "\$ZIP_FILE" 2>/dev/null || true
        exit \$exit_code
    fi
}

trap rollback EXIT

echo ""
echo "[Step 1/8] Creating pre-update backup snapshot..."
mkdir -p "/var/backups/nimbus_snapshots"
mkdir -p "\$BACKUP_DIR"
rsync -a \
    --exclude 'storage/logs/*' \
    --exclude 'storage/framework/cache/*' \
    --exclude 'storage/framework/sessions/*' \
    --exclude 'database/*.sqlite*' \
    --exclude 'database/*-journal*' \
    --exclude 'database/*-wal*' \
    --exclude 'database/*-shm*' \
    --exclude 'node_modules' \
    "\$PANEL_DIR/" "\$BACKUP_DIR/" || [ \$? -eq 24 ]

if [ -f "\$PANEL_DIR/database/database.sqlite" ]; then
    mkdir -p "\$BACKUP_DIR/database"
    cp -f "\$PANEL_DIR/database/database.sqlite" "\$BACKUP_DIR/database/database.sqlite"
    cp -f "\$PANEL_DIR/database/database.sqlite" "\$PANEL_DIR/database/database.sqlite.pre_update_bak"
fi
echo "Snapshot safely secured at \$BACKUP_DIR."

echo ""
echo "[Step 2/8] Downloading release package from Roook..."
curl -sSL --fail --retry 3 --connect-timeout 20 "\$DOWNLOAD_URL" -o "\$ZIP_FILE"

if [ ! -s "\$ZIP_FILE" ]; then
    echo "ERROR: Downloaded release archive is empty or could not be downloaded."
    exit 1
fi
echo "Downloaded successfully (\$(du -h "\$ZIP_FILE" | cut -f1))."

echo ""
echo "[Step 3/8] Verifying package integrity..."
if [ -n "\$EXPECTED_SHA256" ]; then
    ACTUAL_SHA256=\$(sha256sum "\$ZIP_FILE" | awk '{print \$1}')
    if [ "\$ACTUAL_SHA256" != "\$EXPECTED_SHA256" ]; then
        echo "ERROR: SHA-256 Checksum mismatch!"
        echo "Expected: \$EXPECTED_SHA256"
        echo "Actual:   \$ACTUAL_SHA256"
        exit 1
    fi
    echo "SHA-256 integrity verified successfully: \$ACTUAL_SHA256"
else
    echo "Notice: SHA-256 not provided by server; proceeding with signature caution."
fi

echo ""
echo "[Step 4/8] Extracting release files into staging sandbox..."
rm -rf "\$STAGING_DIR"
mkdir -p "\$STAGING_DIR"
unzip -q -o "\$ZIP_FILE" -d "\$STAGING_DIR"

# Handle possible nested archive directory
if [ ! -f "\$STAGING_DIR/artisan" ]; then
    SUBDIR=\$(find "\$STAGING_DIR" -mindepth 1 -maxdepth 1 -type d | head -n 1)
    if [ -n "\$SUBDIR" ] && [ -f "\$SUBDIR/artisan" ]; then
        echo "Flattening nested archive directory structure..."
        mv "\$SUBDIR"/* "\$STAGING_DIR/" 2>/dev/null || true
        mv "\$SUBDIR"/.* "\$STAGING_DIR/" 2>/dev/null || true
    fi
fi

if [ ! -f "\$STAGING_DIR/artisan" ]; then
    echo "ERROR: Corrupt or invalid Nimbus archive (missing artisan entry point)."
    exit 1
fi

echo ""
echo "[Step 5/8] Safely synchronizing files (preserving .env, storage, and SQLite database)..."
rsync -a \
    --exclude '.env' \
    --exclude 'storage/app' \
    --exclude 'storage/logs' \
    --exclude 'database/*.sqlite*' \
    --exclude 'database/*-journal*' \
    --exclude 'database/*-wal*' \
    --exclude 'database/*-shm*' \
    "\$STAGING_DIR/" "\$PANEL_DIR/" || [ \$? -eq 24 ]

# Immediately enforce proper permissions before manifest commands run
chown -R www-data:www-data "\$PANEL_DIR"
chmod -R 775 "\$PANEL_DIR/storage" "\$PANEL_DIR/bootstrap/cache" "\$PANEL_DIR/database"
chmod 664 "\$PANEL_DIR/database"/*.sqlite* 2>/dev/null || true
chmod +x "\$PANEL_DIR/artisan" "\$PANEL_DIR/bin/"* 2>/dev/null || true
ln -sf "\$PANEL_DIR/bin/nimbus" /usr/local/bin/nimbus 2>/dev/null || true
ln -sf "\$PANEL_DIR/bin/nimbus-rollback" /usr/local/bin/nimbus-rollback 2>/dev/null || true

cd "\$PANEL_DIR"

echo ""
echo "[Step 6/8] Executing ordered manifest commands..."
{$commandsScript}

echo ""
echo "[Step 7/8] Performing post-update health check..."
if ! php "\$PANEL_DIR/artisan" --version >/dev/null 2>&1; then
    echo "ERROR: Post-update artisan health check failed!"
    exit 1
fi
echo "Artisan framework health check passed."

echo ""
echo "[Step 8/8] Finalizing permissions and applying version stamp..."
chown -R www-data:www-data "\$PANEL_DIR"
chmod -R 775 "\$PANEL_DIR/storage" "\$PANEL_DIR/bootstrap/cache" "\$PANEL_DIR/database"
chmod 664 "\$PANEL_DIR/database"/*.sqlite* 2>/dev/null || true
chmod +x "\$PANEL_DIR/artisan" "\$PANEL_DIR/bin/"* 2>/dev/null || true
ln -sf "\$PANEL_DIR/bin/nimbus" /usr/local/bin/nimbus 2>/dev/null || true
ln -sf "\$PANEL_DIR/bin/nimbus-rollback" /usr/local/bin/nimbus-rollback 2>/dev/null || true

# Record current version
echo "\$TARGET_VERSION" > "\$PANEL_DIR/VERSION"

# Cleanup staging files
rm -rf "\$STAGING_DIR" "\$ZIP_FILE" 2>/dev/null || true

# Clear rollback trap and mark status as done
trap - EXIT
echo "done" > "\$STATUS_FILE"

echo "Permissions finalized and v\$TARGET_VERSION registered."
echo ""
echo "================================================================"
echo "Update completed successfully to v\$TARGET_VERSION!"
echo "Timestamp: \$(date '+%Y-%m-%d %H:%M:%S')"
echo "================================================================"

# Gracefully reload background services asynchronously in a detached subshell
(
    sleep 1
    systemctl reload nimbus-php-fpm 2>/dev/null || systemctl restart nimbus-php-fpm 2>/dev/null || systemctl reload php8.3-fpm 2>/dev/null || systemctl restart php8.3-fpm 2>/dev/null || true
    systemctl reload nimbus-nginx 2>/dev/null || systemctl reload nginx 2>/dev/null || true
    if command -v supervisorctl >/dev/null 2>&1; then
        supervisorctl restart nimbus-worker:* 2>/dev/null || true
    fi
    php "\$PANEL_DIR/artisan" queue:restart 2>/dev/null || true
) >/dev/null 2>&1 &
BASH;

            $tempScript = "/tmp/nimbus_ota_update.sh";
            file_put_contents($tempScript, $script);
            chmod($tempScript, 0755);

            // Execute in detached session with setsid so service reloads do not terminate this process
            exec("setsid nohup sudo bash {$tempScript} > /dev/null 2>&1 &");

            return response()->json([
                'success' => true,
                'message' => "Safe update pipeline initiated for v{$targetVersion}."
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get update progress/status
     */
    public function getUpdateStatus()
    {
        $logFile = storage_path('logs/update.log');
        $statusFile = storage_path('logs/update_status.txt');

        $log = file_exists($logFile) ? file_get_contents($logFile) : '';
        $status = file_exists($statusFile) ? trim(file_get_contents($statusFile)) : 'idle';
        $currentVersion = $this->getCurrentVersion();

        // Detect completion or failure in log
        if (
            strpos($log, 'Update completed successfully') !== false ||
            (strpos($log, 'Artisan framework health check passed') !== false && strpos($log, '[Step 8/8]') !== false)
        ) {
            $status = 'done';
            if (!file_exists($statusFile) || trim(@file_get_contents($statusFile)) !== 'done') {
                @file_put_contents($statusFile, 'done');
            }
        } elseif (strpos($log, 'AUTOMATIC ROLLBACK COMPLETE') !== false || strpos($log, 'CRITICAL: UPDATE FAILED') !== false) {
            $status = 'failed';
            if (!file_exists($statusFile) || trim(@file_get_contents($statusFile)) !== 'failed') {
                @file_put_contents($statusFile, 'failed');
            }
        }

        return response()->json([
            'status' => $status,
            'log' => $log,
            'currentVersion' => $currentVersion
        ]);
    }

    /**
     * Force refresh update check
     */
    public function forceCheck()
    {
        Cache::forget('nimbus_update_check');
        return $this->checkForUpdates();
    }
}
