<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\BackupDestination;
use App\Models\BackupRecord;
use App\Models\BackupSchedule;
use App\Models\NimbusDatabase;
use App\Models\UserWebsite;
use App\Services\BackupService;
use App\Services\Storage\BackupStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class BackupController extends Controller
{
    protected BackupService $backupService;

    public function __construct(BackupService $backupService)
    {
        $this->backupService = $backupService;
    }

    /**
     * Display the backups dashboard
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $isRoot = $user->isRootOrAdmin();

        // Ensure default local destination exists
        if (BackupDestination::count() === 0) {
            BackupDestination::create([
                'name' => 'Local Server Storage',
                'driver' => 'local',
                'is_default' => true,
                'is_active' => true,
                'credentials' => [],
                'last_test_status' => 'success',
                'last_tested_at' => now(),
            ]);
        }

        // 1. Fetch Backups
        $backupsQuery = BackupRecord::with(['schedule', 'destination'])->orderBy('created_at', 'desc');
        if (!$isRoot) {
            $accessibleDomains = $user->accessibleDomains();
            $backupsQuery->where(function ($q) use ($accessibleDomains) {
                $q->whereIn('domain', $accessibleDomains)
                  ->orWhere('created_by', auth()->user()->email);
            });
        }
        $backups = $backupsQuery->get()->map(function ($b) {
            return [
                'id' => $b->id,
                'schedule_id' => $b->schedule_id,
                'schedule_name' => $b->schedule?->name,
                'destination_id' => $b->destination_id,
                'destination_name' => $b->destination?->name,
                'domain' => $b->domain,
                'database_name' => $b->database_name,
                'type' => $b->type,
                'file_name' => $b->file_name,
                'file_path' => $b->file_path,
                'size_bytes' => $b->size_bytes,
                'formatted_size' => $b->formatted_size,
                'storage_driver' => $b->storage_driver,
                'remote_status' => $b->remote_status ?: 'none',
                'remote_path' => $b->remote_path,
                'remote_error' => $b->remote_error,
                'status' => $b->status,
                'error_message' => $b->error_message,
                'checksum' => $b->checksum,
                'metadata' => $b->metadata,
                'created_by' => $b->created_by,
                'completed_at' => $b->completed_at ? $b->completed_at->toDateTimeString() : null,
                'created_at' => $b->created_at->toDateTimeString(),
            ];
        });

        // 2. Fetch Schedules
        $schedulesQuery = BackupSchedule::with(['destination'])->withCount('records')->orderBy('created_at', 'desc');
        if (!$isRoot) {
            $accessibleDomains = $user->accessibleDomains();
            $schedulesQuery->whereIn('domain', $accessibleDomains);
        }
        $schedules = $schedulesQuery->get()->map(function ($s) {
            return [
                'id' => $s->id,
                'name' => $s->name,
                'domain' => $s->domain,
                'database_name' => $s->database_name,
                'type' => $s->type,
                'frequency' => $s->frequency,
                'time' => $s->time,
                'day_of_week' => $s->day_of_week,
                'day_of_month' => $s->day_of_month,
                'retention_count' => $s->retention_count,
                'destination_id' => $s->destination_id,
                'destination_name' => $s->destination?->name,
                'storage_driver' => $s->storage_driver,
                'email_notifications' => $s->email_notifications,
                'is_active' => $s->is_active,
                'last_run_at' => $s->last_run_at ? $s->last_run_at->toDateTimeString() : null,
                'next_run_at' => $s->next_run_at ? $s->next_run_at->toDateTimeString() : null,
                'last_status' => $s->last_status,
                'last_error' => $s->last_error,
                'records_count' => $s->records_count,
            ];
        });

        // 3. Fetch Destinations
        $destinations = BackupDestination::orderBy('is_default', 'desc')->orderBy('name')->get()->map(function ($d) {
            return [
                'id' => $d->id,
                'name' => $d->name,
                'driver' => $d->driver,
                'is_default' => $d->is_default,
                'is_active' => $d->is_active,
                'credentials' => $d->safe_credentials,
                'raw_credentials_present' => !empty($d->credentials),
                'last_tested_at' => $d->last_tested_at ? $d->last_tested_at->toDateTimeString() : null,
                'last_test_status' => $d->last_test_status,
                'last_test_error' => $d->last_test_error,
            ];
        });

        // 4. Fetch Available Domains & Databases
        $availableDomains = $this->getAvailableDomains();
        $availableDatabases = $this->getAvailableDatabases();

        // 5. Calculate Stats
        $totalBytes = $backups->where('status', 'completed')->sum('size_bytes');
        $totalBackups = $backups->count();
        $activeSchedules = $schedules->where('is_active', true)->count();
        $lastBackup = $backups->first();

        $stats = [
            'total_backups' => $totalBackups,
            'total_size' => $this->formatBytes($totalBytes),
            'total_size_bytes' => $totalBytes,
            'active_schedules' => $activeSchedules,
            'last_backup_at' => $lastBackup ? $lastBackup['created_at'] : 'Never',
            'last_backup_status' => $lastBackup ? $lastBackup['status'] : null,
        ];

        return Inertia::render('Backups/Index', [
            'backups' => $backups,
            'schedules' => $schedules,
            'destinations' => $destinations,
            'domains' => $availableDomains,
            'databases' => $availableDatabases,
            'stats' => $stats,
        ]);
    }

    /**
     * Create an on-demand backup
     */
    public function store(Request $request)
    {
        $request->validate([
            'domain' => 'nullable|string|max:255',
            'database_name' => 'nullable|string|max:64',
            'type' => 'required|in:database,files,full',
            'name' => 'nullable|string|max:100',
            'destination_id' => 'nullable|exists:backup_destinations,id',
            'retention_count' => 'nullable|integer|min:1|max:100',
        ]);

        $domain = $request->input('domain');
        $databaseName = $request->input('database_name');
        $type = $request->input('type');

        if (empty($domain) && empty($databaseName)) {
            return back()->with('error', 'Please select either a domain or a database to backup.');
        }

        // Check permissions
        $user = auth()->user();
        if (!$user->isRootOrAdmin()) {
            if (!empty($domain) && !$user->canAccessDomain($domain)) {
                return back()->with('error', 'You do not have permission to backup this domain.');
            }
            if (!empty($databaseName) && !$user->canAccessDatabase($databaseName)) {
                return back()->with('error', 'You do not have permission to backup this database.');
            }
        }

        try {
            $record = $this->backupService->createBackup([
                'domain' => $domain,
                'database_name' => $databaseName,
                'type' => $type,
                'name' => $request->input('name'),
                'destination_id' => $request->input('destination_id'),
                'retention_count' => (int) $request->input('retention_count', 7),
                'created_by' => $user->email,
            ]);

            ActivityLog::log(
                'CREATE_BACKUP',
                'Backups',
                "Created {$type} backup '{$record->file_name}' for " . ($domain ?: $databaseName)
            );

            return back()->with('success', "Backup completed successfully ({$record->formatted_size}).");

        } catch (\Exception $e) {
            Log::error("Manual backup failed: " . $e->getMessage());
            return back()->with('error', 'Backup failed: ' . $e->getMessage());
        }
    }

    /**
     * Create a new automated schedule
     */
    public function storeSchedule(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'domain' => 'nullable|string|max:255',
            'database_name' => 'nullable|string|max:64',
            'type' => 'required|in:database,files,full',
            'frequency' => 'required|in:hourly,daily,weekly,monthly',
            'time' => 'required|string|regex:/^\d{2}:\d{2}$/',
            'day_of_week' => 'nullable|integer|between:0,6',
            'day_of_month' => 'nullable|integer|between:1,31',
            'retention_count' => 'required|integer|min:1|max:100',
            'destination_id' => 'nullable|exists:backup_destinations,id',
            'email_notifications' => 'boolean',
        ]);

        $domain = $request->input('domain');
        $databaseName = $request->input('database_name');

        if (empty($domain) && empty($databaseName)) {
            return back()->with('error', 'Please select either a domain or a database for the schedule.');
        }

        $schedule = new BackupSchedule($request->only([
            'name', 'domain', 'database_name', 'type', 'frequency', 'time',
            'day_of_week', 'day_of_month', 'retention_count', 'destination_id', 'email_notifications'
        ]));
        $schedule->is_active = true;
        $dest = $schedule->destination_id ? BackupDestination::find($schedule->destination_id) : null;
        $schedule->storage_driver = $dest?->driver ?? 'local';
        $schedule->save();
        $schedule->calculateNextRun();

        ActivityLog::log(
            'CREATE_BACKUP_SCHEDULE',
            'Backups',
            "Created {$schedule->frequency} backup schedule '{$schedule->name}' for " . ($domain ?: $databaseName)
        );

        return back()->with('success', "Backup schedule '{$schedule->name}' created successfully.");
    }

    /**
     * Update an automated schedule
     */
    public function updateSchedule(Request $request, BackupSchedule $schedule)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'domain' => 'nullable|string|max:255',
            'database_name' => 'nullable|string|max:64',
            'type' => 'required|in:database,files,full',
            'frequency' => 'required|in:hourly,daily,weekly,monthly',
            'time' => 'required|string|regex:/^\d{2}:\d{2}$/',
            'day_of_week' => 'nullable|integer|between:0,6',
            'day_of_month' => 'nullable|integer|between:1,31',
            'retention_count' => 'required|integer|min:1|max:100',
            'destination_id' => 'nullable|exists:backup_destinations,id',
            'email_notifications' => 'boolean',
        ]);

        $schedule->fill($request->only([
            'name', 'domain', 'database_name', 'type', 'frequency', 'time',
            'day_of_week', 'day_of_month', 'retention_count', 'destination_id', 'email_notifications'
        ]));
        $dest = $schedule->destination_id ? BackupDestination::find($schedule->destination_id) : null;
        $schedule->storage_driver = $dest?->driver ?? 'local';
        $schedule->save();
        $schedule->calculateNextRun();

        ActivityLog::log(
            'UPDATE_BACKUP_SCHEDULE',
            'Backups',
            "Updated backup schedule '{$schedule->name}'"
        );

        return back()->with('success', "Schedule '{$schedule->name}' updated.");
    }

    /**
     * Toggle schedule active state
     */
    public function toggleSchedule(BackupSchedule $schedule)
    {
        $schedule->is_active = !$schedule->is_active;
        if ($schedule->is_active) {
            $schedule->calculateNextRun();
        }
        $schedule->save();

        $status = $schedule->is_active ? 'enabled' : 'disabled';
        return back()->with('success', "Schedule '{$schedule->name}' has been {$status}.");
    }

    /**
     * Delete an automated schedule
     */
    public function deleteSchedule(BackupSchedule $schedule)
    {
        $name = $schedule->name;
        $schedule->delete();

        ActivityLog::log(
            'DELETE_BACKUP_SCHEDULE',
            'Backups',
            "Deleted backup schedule '{$name}'"
        );

        return back()->with('success', "Schedule '{$name}' was deleted.");
    }

    /**
     * Run a schedule immediately on-demand
     */
    public function runScheduleNow(BackupSchedule $schedule)
    {
        try {
            $this->backupService->createBackup([
                'schedule_id' => $schedule->id,
                'domain' => $schedule->domain,
                'database_name' => $schedule->database_name,
                'type' => $schedule->type,
                'retention_count' => $schedule->retention_count,
                'created_by' => auth()->user()?->email ?: 'Schedule Manual Trigger',
            ]);

            $schedule->update([
                'last_run_at' => now(),
                'last_status' => 'success',
                'last_error' => null,
            ]);
            $schedule->calculateNextRun();

            return back()->with('success', "Schedule '{$schedule->name}' executed successfully.");
        } catch (\Exception $e) {
            return back()->with('error', "Scheduled run failed: " . $e->getMessage());
        }
    }

    /**
     * Restore a backup archive
     */
    public function restore(Request $request, BackupRecord $backup)
    {
        $user = auth()->user();
        if (!$user->isRootOrAdmin()) {
            if (!empty($backup->domain) && !$user->canAccessDomain($backup->domain)) {
                return back()->with('error', 'Permission denied.');
            }
        }

        try {
            // Optional snapshot creation before restore
            if ($request->boolean('create_snapshot_before_restore')) {
                try {
                    $this->backupService->createBackup([
                        'domain' => $backup->domain,
                        'database_name' => $backup->database_name,
                        'type' => $backup->type,
                        'name' => 'Safety Snapshot (Pre-Restore)',
                        'retention_count' => 0, // don't prune snapshots
                        'created_by' => 'Safety Snapshot (Pre-Restore)',
                    ]);
                } catch (\Exception $snapEx) {
                    Log::warning("Pre-restore snapshot creation failed: " . $snapEx->getMessage());
                }
            }

            $this->backupService->restoreBackup($backup);

            return back()->with('success', "Backup '{$backup->file_name}' restored successfully.");
        } catch (\Exception $e) {
            Log::error("Restore failed: " . $e->getMessage());
            return back()->with('error', "Restore failed: " . $e->getMessage());
        }
    }

    /**
     * Download backup file
     */
    public function download(BackupRecord $backup)
    {
        $user = auth()->user();
        if (!$user->isRootOrAdmin()) {
            if (!empty($backup->domain) && !$user->canAccessDomain($backup->domain)) {
                abort(403, 'Permission denied.');
            }
        }

        $filePath = $backup->file_path;
        if (!file_exists($filePath)) {
            // For Linux root files, copy temporarily to storage/app/download if needed
            if (PHP_OS_FAMILY === 'Linux') {
                $tempPath = storage_path('app/temp_dl_' . basename($filePath));
                exec("sudo cp " . escapeshellarg($filePath) . " " . escapeshellarg($tempPath));
                exec("sudo chown www-data:www-data " . escapeshellarg($tempPath));
                if (file_exists($tempPath)) {
                    return response()->download($tempPath, $backup->file_name)->deleteFileAfterSend(true);
                }
            }
            abort(404, 'Backup file not found on disk.');
        }

        return response()->download($filePath, $backup->file_name);
    }

    /**
     * Delete a backup archive and record
     */
    public function destroy(BackupRecord $backup)
    {
        $user = auth()->user();
        if (!$user->isRootOrAdmin()) {
            if (!empty($backup->domain) && !$user->canAccessDomain($backup->domain)) {
                return back()->with('error', 'Permission denied.');
            }
        }

        $fileName = $backup->file_name;
        $this->backupService->deleteBackup($backup);

        ActivityLog::log(
            'DELETE_BACKUP',
            'Backups',
            "Deleted backup archive '{$fileName}'"
        );

        return back()->with('success', "Backup '{$fileName}' was deleted.");
    }

    /**
     * Helper to list domains on the server
     */
    private function getAvailableDomains(): array
    {
        $basePath = '/var/www';
        $domains = [];

        if (File::exists($basePath)) {
            try {
                $directories = File::directories($basePath);
                foreach ($directories as $dir) {
                    $domain = basename($dir);
                    if (!in_array(strtolower($domain), ['html', 'default', 'public', 'cgi-bin', 'nimbus'])) {
                        $associatedDb = $this->backupService->resolveDatabaseForDomain($domain);
                        $domains[] = [
                            'domain' => $domain,
                            'path' => $dir,
                            'associated_db' => $associatedDb
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Failed to list domains for backups: " . $e->getMessage());
            }
        }

        return $domains;
    }

    /**
     * Helper to list databases on the server
     */
    private function getAvailableDatabases(): array
    {
        $databases = [];

        if (PHP_OS_FAMILY === 'Linux') {
            $output = [];
            exec("sudo mysql -N -e 'SHOW DATABASES;' 2>/dev/null", $output);
            $ignored = ['information_schema', 'performance_schema', 'mysql', 'sys'];
            foreach ($output as $db) {
                $db = trim($db);
                if (!empty($db) && !in_array($db, $ignored)) {
                    $databases[] = $db;
                }
            }
        } else {
            $databases = NimbusDatabase::pluck('name')->toArray();
        }

        return $databases;
    }

    private function formatBytes($bytes, $precision = 2)
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Save or update a backup storage destination
     */
    public function saveDestination(Request $request)
    {
        $user = auth()->user();
        if (!$user->isRootOrAdmin()) {
            return back()->with('error', 'Permission denied.');
        }

        $request->validate([
            'id' => 'nullable|exists:backup_destinations,id',
            'name' => 'required|string|max:100',
            'driver' => 'required|in:local,google_drive,backblaze,b2,s3,wasabi,r2,custom_s3',
            'is_default' => 'boolean',
            'credentials' => 'nullable|array',
        ]);

        $destination = null;
        if ($request->filled('id')) {
            $destination = BackupDestination::findOrFail($request->input('id'));
        }

        $isDefault = $request->boolean('is_default');
        if ($isDefault) {
            BackupDestination::where('id', '!=', $destination?->id ?? 0)->update(['is_default' => false]);
        }

        // Merge new credentials with existing credentials so masked fields aren't lost
        $newCreds = $request->input('credentials', []);
        $mergedCreds = [];
        if ($destination && is_array($destination->credentials)) {
            $mergedCreds = $destination->credentials;
        }
        foreach ($newCreds as $k => $v) {
            if (is_string($v) && (str_contains($v, '••••••••') || $v === '[Configured JSON Key]')) {
                continue;
            }
            if ($v !== null && $v !== '') {
                $mergedCreds[$k] = $v;
            }
        }

        if (!$destination) {
            $destination = new BackupDestination();
        }

        $driver = $request->input('driver');
        if ($driver === 'b2') {
            $driver = 'backblaze';
        }

        $destination->name = $request->input('name');
        $destination->driver = $driver;
        $destination->is_default = $isDefault;
        $destination->is_active = true;
        $destination->credentials = $mergedCreds;
        $destination->save();

        ActivityLog::log(
            'SAVE_BACKUP_DESTINATION',
            'Backups',
            "Configured backup destination '{$destination->name}' ({$destination->driver})"
        );

        return back()->with('success', "Storage destination '{$destination->name}' saved successfully.");
    }

    /**
     * Test connection to a storage destination
     */
    public function testDestination(Request $request)
    {
        $user = auth()->user();
        if (!$user->isRootOrAdmin()) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        $destinationId = $request->input('id');
        $driver = $request->input('driver', 'local');
        if ($driver === 'b2') {
            $driver = 'backblaze';
        }
        $creds = $request->input('credentials', []);

        if ($destinationId) {
            $destination = BackupDestination::find($destinationId);
            if ($destination) {
                // Merge credentials
                $existing = $destination->credentials ?: [];
                foreach ($creds as $k => $v) {
                    if (is_string($v) && (str_contains($v, '••••••••') || $v === '[Configured JSON Key]')) {
                        continue;
                    }
                    if ($v !== null && $v !== '') {
                        $existing[$k] = $v;
                    }
                }
                $destination->credentials = $existing;
            }
        } else {
            $destination = new BackupDestination([
                'name' => $request->input('name', 'Test Destination'),
                'driver' => $driver,
                'credentials' => $creds,
            ]);
        }

        $storageService = app(BackupStorageService::class);
        $testResult = $storageService->testConnection($destination);

        if ($destination && $destination->exists) {
            $destination->update([
                'last_tested_at' => now(),
                'last_test_status' => $testResult['success'] ? 'success' : 'failed',
                'last_test_error' => $testResult['success'] ? null : $testResult['message'],
            ]);
        }

        return response()->json($testResult);
    }

    /**
     * Delete a backup storage destination
     */
    public function deleteDestination(BackupDestination $destination)
    {
        $user = auth()->user();
        if (!$user->isRootOrAdmin()) {
            return back()->with('error', 'Permission denied.');
        }

        if ($destination->driver === 'local') {
            return back()->with('error', 'Cannot delete the default Local Server Storage destination.');
        }

        $name = $destination->name;
        $destination->delete();

        ActivityLog::log(
            'DELETE_BACKUP_DESTINATION',
            'Backups',
            "Deleted backup destination '{$name}'"
        );

        return back()->with('success', "Storage destination '{$name}' deleted.");
    }

    /**
     * Set destination as default
     */
    public function setDefaultDestination(BackupDestination $destination)
    {
        $user = auth()->user();
        if (!$user->isRootOrAdmin()) {
            return back()->with('error', 'Permission denied.');
        }

        BackupDestination::query()->update(['is_default' => false]);
        $destination->update(['is_default' => true]);

        return back()->with('success', "'{$destination->name}' is now the default backup destination.");
    }

    /**
     * Retry remote upload for a local backup
     */
    public function retryRemoteUpload(BackupRecord $backup)
    {
        $user = auth()->user();
        if (!$user->isRootOrAdmin()) {
            if (!empty($backup->domain) && !$user->canAccessDomain($backup->domain)) {
                return back()->with('error', 'Permission denied.');
            }
        }

        $result = $this->backupService->retryRemoteUpload($backup);

        if ($result['success']) {
            return back()->with('success', $result['message']);
        } else {
            return back()->with('error', $result['message']);
        }
    }
}

