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

use Illuminate\Support\Facades\Schedule;

Schedule::command('shield:scan /var/www')->dailyAt('03:00');
