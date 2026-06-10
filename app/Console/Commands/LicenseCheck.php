<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LicenseService;
use Illuminate\Support\Facades\Log;

class LicenseCheck extends Command
{
    protected $signature = 'license:check';
    protected $description = 'Verify the license key with VmCoreCentral and send a heartbeat';

    public function handle(LicenseService $licenseService)
    {
        $this->info('[' . now()->toDateTimeString() . '] Running license check...');

        // 1. Force a fresh license verification
        $result = $licenseService->checkLicense(force: true);

        if ($result['status']) {
            $this->info('✅ License is valid. Plan: ' . ($result['plan'] ?? 'unknown'));
            Log::channel('single')->info('License check passed', [
                'plan' => $result['plan'] ?? 'unknown',
                'message' => $result['message'] ?? '',
            ]);
        } else {
            $this->error('❌ License check failed: ' . $result['message']);
            Log::channel('single')->warning('License check failed', [
                'message' => $result['message'],
            ]);
        }

        // 2. Send heartbeat ping
        $this->info('Sending heartbeat...');
        $heartbeatOk = $licenseService->sendHeartbeat();

        if ($heartbeatOk) {
            $this->info('✅ Heartbeat sent successfully.');
        } else {
            $this->warn('⚠️ Heartbeat failed (will retry on next run).');
        }

        return $result['status'] ? 0 : 1;
    }
}
