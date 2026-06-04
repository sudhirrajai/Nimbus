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
        $license = $this->licenseService->checkLicense();

        if ($license['status']) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/Activate', [
            'status' => session('status'),
            'error' => session('error') ?? $license['message'],
            'machineId' => $this->licenseService->getMachineId(),
            'serverIp' => request()->server('SERVER_ADDR') ?? '127.0.0.1',
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

        $this->licenseService->setLicenseKey($request->license_key);
        
        $license = $this->licenseService->checkLicense();

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

        return Inertia::render('Settings/License', [
            'license' => $license,
            'licenseKey' => $licenseKey,
            'machineId' => $this->licenseService->getMachineId(),
            'serverIp' => request()->server('SERVER_ADDR') ?? '127.0.0.1',
        ]);
    }

    /**
     * Sync and refresh the license status
     */
    public function syncLicense()
    {
        $this->licenseService->clearCache();
        $license = $this->licenseService->checkLicense();

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
        $this->licenseService->deactivate();
        return redirect()->route('activate.index')->with('success', 'License deactivated successfully.');
    }
}
