<?php

namespace App\Http\Controllers;

use App\Models\FtpAccount;
use App\Services\FtpService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class FtpAccountController extends Controller
{
    protected FtpService $ftpService;

    public function __construct(FtpService $ftpService)
    {
        $this->ftpService = $ftpService;
    }

    /**
     * Display FTP accounts management page.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $accounts = $this->ftpService->listAccounts($user);
        $connectionInfo = $this->ftpService->getConnectionInfo();
        $accessibleDomains = $user->accessibleDomains();

        // Sort domains alphabetically
        sort($accessibleDomains);

        return Inertia::render('FTP/Index', [
            'accounts' => $accounts,
            'connectionInfo' => $connectionInfo,
            'domains' => $accessibleDomains,
        ]);
    }

    /**
     * Store a newly created FTP account.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'domain' => 'required|string|max:255',
            'username' => 'required|string|min:3|max:64|regex:/^[a-zA-Z0-9_\-\.]+$/',
            'password' => 'required|string|min:6|max:128',
            'homedir' => 'required|string|max:500',
            'quota_mb' => 'nullable|integer|min:0|max:1048576',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = $request->user();

        // Ensure user is authorized for this domain
        if (!$user->canAccessDomain($validated['domain'])) {
            return back()->with('error', 'You are not authorized to create FTP accounts for this domain.');
        }

        try {
            $account = $this->ftpService->createAccount($user, $validated);

            return back()->with('success', "FTP account '{$account->username}' created successfully.");
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            Log::error('FTP account creation failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to create FTP account: ' . $e->getMessage());
        }
    }

    /**
     * Update the password for an FTP account.
     */
    public function updatePassword(Request $request, FtpAccount $account)
    {
        $validated = $request->validate([
            'password' => 'required|string|min:6|max:128',
        ]);

        $user = $request->user();

        if (!$user->canAccessDomain($account->domain)) {
            return back()->with('error', 'Unauthorized action.');
        }

        try {
            $this->ftpService->updatePassword($account, $validated['password']);

            return back()->with('success', "Password for '{$account->username}' updated successfully.");
        } catch (\Exception $e) {
            Log::error('FTP password update failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to update password: ' . $e->getMessage());
        }
    }

    /**
     * Update disk quota.
     */
    public function updateQuota(Request $request, FtpAccount $account)
    {
        $validated = $request->validate([
            'quota_mb' => 'nullable|integer|min:0|max:1048576',
        ]);

        $user = $request->user();

        if (!$user->canAccessDomain($account->domain)) {
            return back()->with('error', 'Unauthorized action.');
        }

        try {
            $this->ftpService->updateQuota($account, $validated['quota_mb'] ?? null);

            return back()->with('success', "Quota for '{$account->username}' updated successfully.");
        } catch (\Exception $e) {
            Log::error('FTP quota update failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to update quota: ' . $e->getMessage());
        }
    }

    /**
     * Toggle active/suspended status.
     */
    public function toggleStatus(Request $request, FtpAccount $account)
    {
        $user = $request->user();

        if (!$user->canAccessDomain($account->domain)) {
            return back()->with('error', 'Unauthorized action.');
        }

        try {
            $newStatus = $this->ftpService->toggleStatus($account);
            $statusText = $newStatus ? 'activated' : 'suspended';

            return back()->with('success', "FTP account '{$account->username}' {$statusText}.");
        } catch (\Exception $e) {
            Log::error('FTP toggle status failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to toggle account status: ' . $e->getMessage());
        }
    }

    /**
     * Delete an FTP account.
     */
    public function destroy(Request $request, FtpAccount $account)
    {
        $user = $request->user();

        if (!$user->canAccessDomain($account->domain)) {
            return back()->with('error', 'Unauthorized action.');
        }

        try {
            $username = $account->username;
            $this->ftpService->deleteAccount($account);

            return back()->with('success', "FTP account '{$username}' deleted successfully.");
        } catch (\Exception $e) {
            Log::error('FTP account deletion failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to delete FTP account: ' . $e->getMessage());
        }
    }
}
