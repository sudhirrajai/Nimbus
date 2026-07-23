<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('shield:scan {path}', function ($path) {
    $this->info("Starting security scan for: $path");
    $controller = new \App\Http\Controllers\ShieldController();
    $controller->runInternalScan($path);
    $this->info("Scan completed.");
})->purpose('Run a security scan in background');

Artisan::command('shield:scan-file {filePath}', function ($filePath) {
    $finding = \App\Http\Controllers\ShieldController::scanFile($filePath);
    if ($finding) {
        \App\Models\SecurityThreat::updateOrCreate(
            ['file_path' => $filePath],
            [
                'type' => $finding['type'],
                'details' => $finding['details'] . ' (Detected during upload)',
                'status' => 'detected',
                'detected_at' => now()
            ]
        );
    }
})->purpose('Scan a single uploaded file in background');

use Illuminate\Support\Facades\Schedule;
use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

$scanTime = '03:00';
try {
    if (class_exists(Setting::class) && Schema::hasTable('settings')) {
        $scanTime = Setting::where('key', 'shield_auto_scan_time')->value('value') ?: '03:00';
    }
} catch (\Throwable $e) {
    // Fail-safe during installation / migrations
}

Schedule::command('shield:scan /var/www')
    ->dailyAt($scanTime)
    ->when(function () {
        try {
            if (class_exists(Setting::class) && Schema::hasTable('settings')) {
                return Setting::where('key', 'shield_auto_scan')->value('value') === '1';
            }
        } catch (\Throwable $e) {
            // Fail-safe
        }
        return false;
    });

Schedule::command('monitor:resources')->everyFiveMinutes();
Schedule::command('activity-log:clean')->dailyAt('00:00');

// ─── License Security Checks ─────────────────────────────────────────────
// Hourly full verification (forces network call to VmCoreCentral)
Schedule::command('license:check')->hourly();

// Lightweight heartbeat every 5 minutes
Schedule::call(function () {
    try {
        app(\App\Services\LicenseService::class)->sendHeartbeat();
    } catch (\Throwable $e) {
        // Fail silently — the hourly check handles grace period
    }
})->everyFiveMinutes();
