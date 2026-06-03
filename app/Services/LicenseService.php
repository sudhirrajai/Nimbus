<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LicenseService
{
    protected $apiUrl;

    public function __construct()
    {
        $this->apiUrl = rtrim(env('VMCORE_API_URL', 'http://localhost:8001'), '/') . '/api/v1/verify';
    }

    /**
     * Check if the license is valid
     */
    public function checkLicense()
    {
        return Cache::remember('license_status', 86400, function () {
            $licenseKey = $this->getLicenseKey();

            if (!$licenseKey) {
                return ['status' => false, 'message' => 'No license key found.'];
            }

            try {
                $rootUser = \App\Models\User::where('role', 'root')->first();

                $response = Http::timeout(10)->post($this->apiUrl, [
                    'license_key' => $licenseKey,
                    'server_ip' => request()->server('SERVER_ADDR') ?? '127.0.0.1',
                    'machine_id' => $this->getMachineId(),
                    'domain' => request()->getHost(),
                    'admin_name' => $rootUser ? $rootUser->name : null,
                    'admin_email' => $rootUser ? $rootUser->email : null,
                ]);

                if ($response->successful()) {
                    return $response->json();
                }

                return [
                    'status' => false,
                    'message' => $response->json('message') ?? 'Verification failed.'
                ];
            } catch (\Exception $e) {
                // If API is down, we might want to allow access if it was previously valid
                // For now, return false to be secure, or implement a "grace period"
                return [
                    'status' => false,
                    'message' => 'Could not connect to licensing server.'
                ];
            }
        });
    }

    /**
     * Get the stored license key
     */
    public function getLicenseKey()
    {
        return DB::table('settings')->where('key', 'license_key')->value('value');
    }

    /**
     * Set the license key
     */
    public function setLicenseKey($key)
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'license_key'],
            ['value' => $key, 'updated_at' => now()]
        );
        Cache::forget('license_status');
    }

    /**
     * Generate or retrieve a unique machine ID
     */
    public function getMachineId()
    {
        $path = storage_path('app/machine_id');
        
        if (file_exists($path)) {
            return trim(file_get_contents($path));
        }

        $id = hash('sha256', php_uname() . node_machine_id()); // Fallback or custom logic
        file_put_contents($path, $id);
        
        return $id;
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
