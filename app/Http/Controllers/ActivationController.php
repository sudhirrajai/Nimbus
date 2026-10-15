<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\LicenseService;
use Inertia\Inertia;

class ActivationController extends Controller
{
    protected $licenseService;

    public function __construct(LicenseService $licenseService)
    {
        $this->licenseService = $licenseService;
    }

    /**
     * Show the activation page
     */
    public function index()
    {
        // Clear lock so we can attempt a fresh check
        $this->licenseService->clearLock();

        $license = $this->licenseService->checkLicense(force: true);

        if ($license['status']) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/Activate', [
            'status'    => session('status'),
            'error'     => session('error') ?? $license['message'],
            'machineId' => $this->licenseService->getMachineId(),
            'serverIp'  => request()->server('SERVER_ADDR') ?? '127.0.0.1',
        ]);
    }

    /**
     * Activate the license
     */
    public function activate(Request $request)
    {
        $request->validate([
            'license_key' => 'required|string',
        ]);

        // Clear any existing lock before attempting activation
        $this->licenseService->clearLock();

        $this->licenseService->setLicenseKey($request->license_key);

        // Force a fresh check against VmCoreCentral
        $license = $this->licenseService->checkLicense(force: true);

        if ($license['status']) {
            return redirect()->route('dashboard')->with('success', 'License activated successfully!');
        }

        return back()->with('error', $license['message'] ?? 'Invalid license key.');
    }

    /**
     * Show the license settings page
     */
    public function showLicenseSettings()
    {
        $license = $this->licenseService->checkLicense();
        $licenseKey = $this->licenseService->getLicenseKey();
        $tokenData = $this->licenseService->getLicenseToken();

        $expiresRaw = $license['expires_at'] ?? ($tokenData['expires_at'] ?? null);
        $expiresFormatted = null;
        if (!empty($expiresRaw) && strtolower($expiresRaw) !== 'never') {
            $ts = strtotime($expiresRaw);
            $expiresFormatted = $ts ? date('F j, Y', $ts) : $expiresRaw;
        }

        $maxServers = $license['max_servers'] ?? ($tokenData['max_servers'] ?? $this->licenseService->getMaxServers());
        $activeServers = $license['active_servers'] ?? ($tokenData['active_servers'] ?? $this->licenseService->getActiveServers());
        $maxDomains = $license['max_domains'] ?? ($tokenData['max_domains'] ?? (int) ($this->licenseService->getSetting('license_max_domains') ?: 3));

        $licenseData = array_merge($license, [
            'expires_at'     => $expiresFormatted,
            'max_servers'    => (int) $maxServers,
            'active_servers' => (int) $activeServers,
            'max_domains'    => (int) $maxDomains,
        ]);

        return Inertia::render('Settings/License', [
            'license'       => $licenseData,
            'licenseKey'    => $licenseKey,
            'machineId'     => $this->licenseService->getMachineId(),
            'serverIp'      => request()->server('SERVER_ADDR') ?? '127.0.0.1',
            'plan'          => $tokenData['plan'] ?? ($license['plan'] ?? 'free'),
            'maxDomains'    => (int) $maxDomains,
            'maxServers'    => (int) $maxServers,
            'activeServers' => (int) $activeServers,
        ]);
    }

    /**
     * Sync and refresh the license status
     */
    public function syncLicense()
    {
        $this->licenseService->clearCache();
        $license = $this->licenseService->checkLicense(force: true);

        if ($license['status']) {
            return back()->with('success', 'License status synchronized. ' . ($license['message'] ?? 'License is active.'));
        }

        return back()->with('error', 'Sync failed: ' . ($license['message'] ?? 'Invalid license key.'));
    }

    /**
     * Deactivate and remove the license
     */
    public function deactivateLicense()
    {
        if (auth()->user()?->role !== 'root') {
            abort(403, 'Unauthorized. Only the root administrator can deactivate the license.');
        }

        $this->licenseService->deactivate();
        return redirect()->route('activate.index')->with('success', 'License deactivated successfully.');
    }
}
