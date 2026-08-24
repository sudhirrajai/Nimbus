<?php

namespace App\Services;

use App\Models\BackupRecord;
use App\Models\BackupSchedule;
use App\Models\NimbusDatabase;
use App\Models\Setting;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class BackupService
{
    /**
     * Get the base directory for storing backups
     */
    public static function getBackupDirectory(): string
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $dir = '/var/backups/nimbus';
        } else {
            $dir = storage_path('app/backups');
        }

        if (!File::exists($dir)) {
            try {
                if (PHP_OS_FAMILY === 'Linux') {
                    exec("sudo mkdir -p " . escapeshellarg($dir));
                    exec("sudo chmod 700 " . escapeshellarg($dir));
                } else {
                    File::makeDirectory($dir, 0755, true);
                }
            } catch (\Exception $e) {
                Log::warning("Failed to create backup directory: " . $e->getMessage());
            }
        }

        return $dir;
    }

    /**
     * Create a backup on-demand or from schedule
     */
    public function createBackup(array $params): BackupRecord
    {
        $domain = $params['domain'] ?? null;
        $databaseName = $params['database_name'] ?? null;
        $type = $params['type'] ?? 'full'; // 'database', 'files', 'full'
        $scheduleId = $params['schedule_id'] ?? null;
        $createdBy = $params['created_by'] ?? (auth()->user()?->email ?? 'System');
        $customName = $params['name'] ?? null;

        $targetName = $domain ?: ($databaseName ?: 'server');
        $safeTarget = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', strtolower($targetName));
        $timestamp = date('Ymd_His');
        $randomSuffix = Str::random(6);

        $backupDir = self::getBackupDirectory();
        $domainBackupDir = $backupDir . '/' . $safeTarget;

        if (!File::exists($domainBackupDir)) {
            if (PHP_OS_FAMILY === 'Linux') {
                exec("sudo mkdir -p " . escapeshellarg($domainBackupDir));
                exec("sudo chmod 700 " . escapeshellarg($domainBackupDir));
            } else {
                File::makeDirectory($domainBackupDir, 0755, true);
            }
        }

        $fileName = "backup_{$safeTarget}_{$type}_{$timestamp}_{$randomSuffix}.tar.gz";
        if ($type === 'database') {
            $fileName = "backup_{$safeTarget}_db_{$timestamp}_{$randomSuffix}.sql.gz";
        }
        $filePath = $domainBackupDir . '/' . $fileName;

        // Create pending record
        $record = BackupRecord::create([
            'schedule_id' => $scheduleId,
            'domain' => $domain,
            'database_name' => $databaseName,
            'type' => $type,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'size_bytes' => 0,
            'storage_driver' => 'local',
            'status' => 'in_progress',
            'created_by' => $createdBy,
            'metadata' => [
                'custom_name' => $customName,
                'target' => $targetName,
                'started_at' => now()->toDateTimeString(),
            ]
        ]);

        try {
            $metadata = $record->metadata ?: [];

            if ($type === 'database') {
                $this->dumpDatabase($databaseName ?: $this->resolveDatabaseForDomain($domain), $filePath);
            } elseif ($type === 'files') {
                $this->archiveFiles($domain, $filePath);
            } else {
                // Full (Both)
                $this->archiveFull($domain, $databaseName ?: $this->resolveDatabaseForDomain($domain), $filePath, $metadata);
            }

            // Verify file creation and calculate size & checksum
            $size = 0;
            $checksum = null;

            if (PHP_OS_FAMILY === 'Linux') {
                $sizeOutput = [];
                exec("sudo stat -c%s " . escapeshellarg($filePath) . " 2>/dev/null", $sizeOutput);
                $size = isset($sizeOutput[0]) ? (int)trim($sizeOutput[0]) : 0;

                $hashOutput = [];
                exec("sudo sha256sum " . escapeshellarg($filePath) . " 2>/dev/null", $hashOutput);
                if (!empty($hashOutput[0])) {
                    $checksum = explode(' ', trim($hashOutput[0]))[0];
                }
            } else {
                if (file_exists($filePath)) {
                    $size = filesize($filePath);
                    $checksum = hash_file('sha256', $filePath);
                }
            }

            if ($size === 0) {
                throw new \Exception("Backup file was not generated or has 0 bytes.");
            }

            $metadata['completed_at'] = now()->toDateTimeString();

            $record->update([
                'status' => 'completed',
                'size_bytes' => $size,
                'checksum' => $checksum,
                'metadata' => $metadata,
                'completed_at' => now(),
            ]);

            // Auto prune old backups for this target if retention count is set
            $retention = $params['retention_count'] ?? 7;
            if ($retention > 0) {
                $this->pruneOldBackups($domain, $databaseName, $type, $retention);
            }

            // Send notification email
            $this->sendEmailNotification('success', $record);

            return $record;

        } catch (\Exception $e) {
            Log::error("Backup creation failed for {$targetName}: " . $e->getMessage());

            $record->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            // Send failure notification email
            $this->sendEmailNotification('failed', $record, $e->getMessage());

            throw $e;
        }
    }

    /**
     * Dump a MySQL database to a compressed .sql.gz file
     */
    private function dumpDatabase(?string $dbName, string $targetGzPath): void
    {
        if (empty($dbName)) {
            throw new \Exception("No database specified or associated with this target.");
        }

        if (PHP_OS_FAMILY === 'Linux') {
            $escapedDb = escapeshellarg($dbName);
            $escapedPath = escapeshellarg($targetGzPath);
            $cmd = "sudo mysqldump --single-transaction --quick --routines --triggers --hex-blob {$escapedDb} 2>/dev/null | gzip -9 > {$escapedPath}";
            
            $output = [];
            $code = 0;
            exec($cmd, $output, $code);

            if ($code !== 0 || !file_exists($targetGzPath) || filesize($targetGzPath) === 0) {
                // Fallback attempt without sudo if root credentials are in my.cnf
                $cmdAlt = "mysqldump --single-transaction --quick {$escapedDb} | gzip -9 > {$escapedPath}";
                exec($cmdAlt, $output, $code);
                if ($code !== 0) {
                    throw new \Exception("Database dump failed for '{$dbName}'. Code: {$code}");
                }
            }
        } else {
            // Development fallback on Windows
            $dummySql = "-- Nimbus Development Backup for DB: {$dbName}\n-- Created: " . date('Y-m-d H:i:s') . "\nCREATE DATABASE IF NOT EXISTS `{$dbName}`;\n";
            $gzContent = gzencode($dummySql, 9);
            File::put($targetGzPath, $gzContent);
        }
    }

    /**
     * Archive website / project directory to .tar.gz
     */
    private function archiveFiles(?string $domain, string $targetGzPath): void
    {
        if (empty($domain)) {
            throw new \Exception("No domain / project specified for file backup.");
        }

        $sourcePath = $this->resolvePathForDomain($domain);
        if (!File::exists($sourcePath)) {
            throw new \Exception("Project directory '{$sourcePath}' does not exist.");
        }

        if (PHP_OS_FAMILY === 'Linux') {
            $escapedSource = escapeshellarg($sourcePath);
            $escapedTarget = escapeshellarg($targetGzPath);

            $excludes = [
                "--exclude='.git'",
                "--exclude='node_modules'",
                "--exclude='storage/logs/*'",
                "--exclude='storage/framework/cache/*'",
                "--exclude='storage/framework/sessions/*'",
                "--exclude='var/cache/*'",
                "--exclude='wp-content/cache/*'",
                "--exclude='vendor/phpunit'",
            ];
            $excludeStr = implode(' ', $excludes);

            $cmd = "sudo tar -czf {$escapedTarget} {$excludeStr} -C {$escapedSource} . 2>&1";
            $output = [];
            $code = 0;
            exec($cmd, $output, $code);

            // tar exit code 1 means "files changed as we read them", which is acceptable for active sites
            if ($code > 1) {
                throw new \Exception("File archiving failed for '{$domain}': " . implode("\n", $output));
            }
        } else {
            // Windows / Dev fallback
            $zipContent = gzencode("Nimbus Project Files Backup for {$domain}\nTimestamp: " . date('Y-m-d H:i:s'), 9);
            File::put($targetGzPath, $zipContent);
        }
    }

    /**
     * Create full backup containing both DB dump, files archive, and manifest
     */
    private function archiveFull(?string $domain, ?string $dbName, string $targetGzPath, array &$metadata): void
    {
        $tempDir = '/tmp/nimbus_bkp_' . uniqid();
        if (PHP_OS_FAMILY !== 'Linux') {
            $tempDir = storage_path('app/temp/nimbus_bkp_' . uniqid());
        }

        File::makeDirectory($tempDir, 0755, true);

        try {
            $hasDb = false;
            $hasFiles = false;

            // 1. Dump database if present
            if (!empty($dbName)) {
                $dbGzPath = $tempDir . '/database.sql.gz';
                $this->dumpDatabase($dbName, $dbGzPath);
                $hasDb = true;
                $metadata['included_database'] = $dbName;
            }

            // 2. Archive files if domain present
            if (!empty($domain)) {
                $filesGzPath = $tempDir . '/files.tar.gz';
                $this->archiveFiles($domain, $filesGzPath);
                $hasFiles = true;
                $metadata['included_domain'] = $domain;
            }

            if (!$hasDb && !$hasFiles) {
                throw new \Exception("Cannot create full backup: Neither database nor domain files could be resolved.");
            }

            // 3. Write manifest.json
            $manifest = [
                'nimbus_version' => '1.0.0',
                'created_at' => now()->toDateTimeString(),
                'domain' => $domain,
                'database' => $dbName,
                'type' => 'full',
                'server_os' => PHP_OS_FAMILY,
                'php_version' => PHP_VERSION,
            ];
            File::put($tempDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

            // 4. Bundle temp directory into final archive
            if (PHP_OS_FAMILY === 'Linux') {
                $escapedTemp = escapeshellarg($tempDir);
                $escapedTarget = escapeshellarg($targetGzPath);
                $cmd = "sudo tar -czf {$escapedTarget} -C {$escapedTemp} . 2>&1";
                $output = [];
                $code = 0;
                exec($cmd, $output, $code);

                if ($code > 1) {
                    throw new \Exception("Full archive bundling failed: " . implode("\n", $output));
                }
            } else {
                $bundleContent = gzencode(json_encode($manifest), 9);
                File::put($targetGzPath, $bundleContent);
            }
        } finally {
            // Clean up temporary workspace
            if (PHP_OS_FAMILY === 'Linux') {
                exec("sudo rm -rf " . escapeshellarg($tempDir));
            } else {
                File::deleteDirectory($tempDir);
            }
        }
    }

    /**
     * Restore a backup archive
     */
    public function restoreBackup(BackupRecord $record, array $options = []): bool
    {
        $filePath = $record->file_path;
        if (!File::exists($filePath) && PHP_OS_FAMILY !== 'Linux') {
            throw new \Exception("Backup file does not exist at: {$filePath}");
        }

        $type = $record->type;
        $domain = $record->domain;
        $dbName = $record->database_name ?: $this->resolveDatabaseForDomain($domain);

        try {
            if ($type === 'database') {
                $this->restoreDatabaseDump($filePath, $dbName);
            } elseif ($type === 'files') {
                $this->restoreFilesArchive($filePath, $domain);
            } elseif ($type === 'full') {
                $this->restoreFullArchive($filePath, $domain, $dbName);
            }

            // Log activity & send email notification
            \App\Models\ActivityLog::log(
                'RESTORE_BACKUP',
                'Backups',
                "Restored {$type} backup '{$record->file_name}' for " . ($domain ?: $dbName)
            );

            $this->sendEmailNotification('restore_success', $record);
            return true;

        } catch (\Exception $e) {
            Log::error("Backup restoration failed for '{$record->file_name}': " . $e->getMessage());
            $this->sendEmailNotification('restore_failed', $record, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Restore database from .sql.gz
     */
    private function restoreDatabaseDump(string $gzPath, ?string $dbName): void
    {
        if (empty($dbName)) {
            throw new \Exception("No target database specified for restoration.");
        }

        if (PHP_OS_FAMILY === 'Linux') {
            $escapedDb = escapeshellarg($dbName);
            $escapedPath = escapeshellarg($gzPath);

            // Ensure database exists
            exec("sudo mysql -e " . escapeshellarg("CREATE DATABASE IF NOT EXISTS `{$dbName}`"));

            // Import gzipped SQL directly
            $cmd = "gunzip -c {$escapedPath} | sudo mysql {$escapedDb} 2>&1";
            $output = [];
            $code = 0;
            exec($cmd, $output, $code);

            if ($code !== 0) {
                throw new \Exception("Database restore failed: " . implode("\n", $output));
            }
        }
    }

    /**
     * Restore files from .tar.gz into domain root
     */
    private function restoreFilesArchive(string $gzPath, ?string $domain): void
    {
        if (empty($domain)) {
            throw new \Exception("No target domain specified for files restoration.");
        }

        $targetPath = $this->resolvePathForDomain($domain);
        if (!File::exists($targetPath)) {
            if (PHP_OS_FAMILY === 'Linux') {
                exec("sudo mkdir -p " . escapeshellarg($targetPath));
            } else {
                File::makeDirectory($targetPath, 0755, true);
            }
        }

        if (PHP_OS_FAMILY === 'Linux') {
            $escapedTarget = escapeshellarg($targetPath);
            $escapedPath = escapeshellarg($gzPath);

            // Extract archive
            $cmd = "sudo tar -xzf {$escapedPath} -C {$escapedTarget} 2>&1";
            $output = [];
            $code = 0;
            exec($cmd, $output, $code);

            if ($code !== 0) {
                throw new \Exception("Files extraction failed: " . implode("\n", $output));
            }

            // Restore www-data permissions
            exec("sudo chown -R www-data:www-data {$escapedTarget}");
        }
    }

    /**
     * Restore a full backup bundle
     */
    private function restoreFullArchive(string $gzPath, ?string $domain, ?string $dbName): void
    {
        $tempDir = '/tmp/nimbus_rst_' . uniqid();
        if (PHP_OS_FAMILY !== 'Linux') {
            $tempDir = storage_path('app/temp/nimbus_rst_' . uniqid());
        }

        File::makeDirectory($tempDir, 0755, true);

        try {
            if (PHP_OS_FAMILY === 'Linux') {
                $escapedTemp = escapeshellarg($tempDir);
                $escapedPath = escapeshellarg($gzPath);
                exec("sudo tar -xzf {$escapedPath} -C {$escapedTemp} 2>&1");
            }

            // 1. Restore database if db dump exists in bundle
            $dbDump = $tempDir . '/database.sql.gz';
            if (file_exists($dbDump)) {
                $this->restoreDatabaseDump($dbDump, $dbName);
            }

            // 2. Restore files if files archive exists in bundle
            $filesArchive = $tempDir . '/files.tar.gz';
            if (file_exists($filesArchive)) {
                $this->restoreFilesArchive($filesArchive, $domain);
            }
        } finally {
            if (PHP_OS_FAMILY === 'Linux') {
                exec("sudo rm -rf " . escapeshellarg($tempDir));
            } else {
                File::deleteDirectory($tempDir);
            }
        }
    }

    /**
     * Delete a backup archive and record
     */
    public function deleteBackup(BackupRecord $record): bool
    {
        $filePath = $record->file_path;
        if (!empty($filePath)) {
            if (PHP_OS_FAMILY === 'Linux') {
                exec("sudo rm -f " . escapeshellarg($filePath));
            } else {
                if (file_exists($filePath)) {
                    File::delete($filePath);
                }
            }
        }

        return (bool) $record->delete();
    }

    /**
     * Automatically prune old backups exceeding the retention threshold
     */
    public function pruneOldBackups(?string $domain, ?string $databaseName, string $type, int $retentionCount): void
    {
        if ($retentionCount <= 0) {
            return;
        }

        $query = BackupRecord::where('type', $type)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc');

        if (!empty($domain)) {
            $query->where('domain', $domain);
        } elseif (!empty($databaseName)) {
            $query->where('database_name', $databaseName);
        }

        $backups = $query->get();

        if ($backups->count() > $retentionCount) {
            $toDelete = $backups->slice($retentionCount);
            foreach ($toDelete as $oldBackup) {
                Log::info("Pruning old backup #{$oldBackup->id} ({$oldBackup->file_name}) for retention policy.");
                $this->deleteBackup($oldBackup);
            }
        }
    }

    /**
     * Execute any backup schedules that are due
     */
    public function runScheduledBackups(): int
    {
        $now = Carbon::now();
        $schedules = BackupSchedule::where('is_active', true)
            ->where(function ($query) use ($now) {
                $query->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', $now);
            })
            ->get();

        $executed = 0;

        foreach ($schedules as $schedule) {
            try {
                Log::info("Executing scheduled backup #{$schedule->id} ({$schedule->name}) for target: " . ($schedule->domain ?: $schedule->database_name));

                $this->createBackup([
                    'schedule_id' => $schedule->id,
                    'domain' => $schedule->domain,
                    'database_name' => $schedule->database_name,
                    'type' => $schedule->type,
                    'retention_count' => $schedule->retention_count,
                    'created_by' => "Schedule: " . ($schedule->name ?: "ID {$schedule->id}"),
                ]);

                $schedule->update([
                    'last_run_at' => now(),
                    'last_status' => 'success',
                    'last_error' => null,
                ]);

                $schedule->calculateNextRun();
                $executed++;

            } catch (\Exception $e) {
                Log::error("Scheduled backup #{$schedule->id} failed: " . $e->getMessage());

                $schedule->update([
                    'last_run_at' => now(),
                    'last_status' => 'failed',
                    'last_error' => $e->getMessage(),
                ]);

                $schedule->calculateNextRun();
            }
        }

        return $executed;
    }

    /**
     * Resolve website document root for a domain
     */
    private function resolvePathForDomain(string $domain): string
    {
        $basePath = '/var/www/' . $domain;
        if (PHP_OS_FAMILY !== 'Linux') {
            return storage_path('app/www/' . $domain);
        }
        return $basePath;
    }

    /**
     * Resolve associated database for a domain
     */
    public function resolveDatabaseForDomain(?string $domain): ?string
    {
        if (empty($domain)) {
            return null;
        }

        // 1. Check NimbusDatabase manual/scanned mapping
        $nimbusDb = NimbusDatabase::where('domain', $domain)->first();
        if ($nimbusDb && !empty($nimbusDb->name)) {
            return $nimbusDb->name;
        }

        // 2. Check WordPressSite table
        $wpSite = \App\Models\WordPressSite::where('domain', $domain)->first();
        if ($wpSite && !empty($wpSite->db_name)) {
            return $wpSite->db_name;
        }

        // 3. Scan .env or wp-config if available
        $envPath = "/var/www/{$domain}/.env";
        if (file_exists($envPath)) {
            $content = @file_get_contents($envPath);
            if ($content && preg_match('/^\s*DB_DATABASE\s*=\s*(.+)$/m', $content, $matches)) {
                return trim($matches[1], "\"' \r\n");
            }
        }

        $wpConfig = "/var/www/{$domain}/wp-config.php";
        if (file_exists($wpConfig)) {
            $content = @file_get_contents($wpConfig);
            if ($content && preg_match('/define\(\s*[\'"]DB_NAME[\'"]\s*,\s*[\'"](.+)[\'"]\s*\)/', $content, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    /**
     * Send email notifications on backup operations
     */
    private function sendEmailNotification(string $eventType, BackupRecord $record, ?string $error = null): void
    {
        try {
            $target = $record->domain ?: ($record->database_name ?: 'System');
            $typeLabel = ucfirst($record->type);
            $size = $record->formatted_size;
            $time = now()->toDateTimeString();

            if ($eventType === 'success') {
                $subject = "✅ Backup Completed: {$target} ({$typeLabel})";
                $html = <<<HTML
<div style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">
    <h2 style="color: #2dce89;">Nimbus Backup Successful</h2>
    <p>A new backup has been created and verified successfully on your Nimbus server.</p>
    <table style="width: 100%; max-width: 500px; border-collapse: collapse; margin-top: 15px;">
        <tr style="border-bottom: 1px solid #eee;">
            <td style="padding: 8px 0; font-weight: bold;">Target:</td>
            <td style="padding: 8px 0;">{$target}</td>
        </tr>
        <tr style="border-bottom: 1px solid #eee;">
            <td style="padding: 8px 0; font-weight: bold;">Backup Type:</td>
            <td style="padding: 8px 0;">{$typeLabel}</td>
        </tr>
        <tr style="border-bottom: 1px solid #eee;">
            <td style="padding: 8px 0; font-weight: bold;">File Size:</td>
            <td style="padding: 8px 0;">{$size}</td>
        </tr>
        <tr style="border-bottom: 1px solid #eee;">
            <td style="padding: 8px 0; font-weight: bold;">Archive File:</td>
            <td style="padding: 8px 0; font-family: monospace; font-size: 12px;">{$record->file_name}</td>
        </tr>
        <tr style="border-bottom: 1px solid #eee;">
            <td style="padding: 8px 0; font-weight: bold;">Completed At:</td>
            <td style="padding: 8px 0;">{$time}</td>
        </tr>
    </table>
    <p style="margin-top: 20px; font-size: 13px; color: #777;">You can manage and download this backup in your Nimbus panel.</p>
</div>
HTML;
                NotificationService::send($subject, $html);

            } elseif ($eventType === 'failed') {
                $subject = "🚨 Backup FAILED: {$target} ({$typeLabel})";
                $errorMsg = htmlspecialchars($error ?: 'Unknown error');
                $html = <<<HTML
<div style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">
    <h2 style="color: #f5365c;">Nimbus Backup Failed</h2>
    <p>An error occurred while creating the backup for <strong>{$target}</strong> on your Nimbus server.</p>
    <div style="background: #fff5f5; border-left: 4px solid #f5365c; padding: 12px; margin: 15px 0;">
        <strong>Error Details:</strong><br>
        <span style="font-family: monospace; font-size: 13px; color: #c00;">{$errorMsg}</span>
    </div>
    <table style="width: 100%; max-width: 500px; border-collapse: collapse; margin-top: 15px;">
        <tr style="border-bottom: 1px solid #eee;">
            <td style="padding: 8px 0; font-weight: bold;">Target:</td>
            <td style="padding: 8px 0;">{$target}</td>
        </tr>
        <tr style="border-bottom: 1px solid #eee;">
            <td style="padding: 8px 0; font-weight: bold;">Type:</td>
            <td style="padding: 8px 0;">{$typeLabel}</td>
        </tr>
        <tr style="border-bottom: 1px solid #eee;">
            <td style="padding: 8px 0; font-weight: bold;">Time:</td>
            <td style="padding: 8px 0;">{$time}</td>
        </tr>
    </table>
    <p style="margin-top: 20px; font-size: 13px; color: #777;">Please log in to your Nimbus panel to investigate disk space and server permissions.</p>
</div>
HTML;
                NotificationService::send($subject, $html);

            } elseif ($eventType === 'restore_success') {
                $subject = "🔄 Backup Restored: {$target} ({$typeLabel})";
                $html = <<<HTML
<div style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">
    <h2 style="color: #11cdef;">Nimbus Restore Successful</h2>
    <p>A backup snapshot was successfully restored on your Nimbus server for <strong>{$target}</strong>.</p>
    <p><strong>Archive:</strong> {$record->file_name}<br><strong>Time:</strong> {$time}</p>
</div>
HTML;
                NotificationService::send($subject, $html);
            }
        } catch (\Exception $e) {
            Log::warning("Failed to send backup email notification: " . $e->getMessage());
        }
    }
}
