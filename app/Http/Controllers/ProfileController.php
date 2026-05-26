<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class ProfileController extends Controller
{
    /**
     * Display profile page
     */
    public function index()
    {
        return Inertia::render('Profile/Index', [
            'user' => Auth::user()
        ]);
    }

    /**
     * Update profile information
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        return back()->with('success', 'Profile updated successfully');
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors([
                'current_password' => 'The current password is incorrect.'
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->with('success', 'Password changed successfully');
    }

    /**
     * Display settings page
     */
    public function settings()
    {
        $versionFile = base_path('VERSION');
        $panelVersion = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '1.0.0';
        
        return Inertia::render('Settings/Index', [
            'panelVersion' => $panelVersion,
            'laravelVersion' => app()->version(),
            'phpVersion' => PHP_VERSION
        ]);
    }

    /**
     * Get panel settings
     */
    public function getSettings()
    {
        $dbSettings = Setting::all()->pluck('value', 'key')->toArray();

        $panelDomain = $dbSettings['panel_domain'] ?? '';
        
        // Auto-detect domain if not set but accessed via hostname
        if (empty($panelDomain) && !request()->isIp()) {
            $panelDomain = request()->getHost();
        }

        $settings = [
            'panel_name' => $dbSettings['panel_name'] ?? config('app.name', 'Nimbus'),
            'timezone' => $dbSettings['timezone'] ?? config('app.timezone', 'UTC'),
            'auto_refresh' => ($dbSettings['auto_refresh'] ?? '1') === '1',
            'session_lifetime' => (int)($dbSettings['session_lifetime'] ?? config('session.lifetime', 120)),
            'panel_domain' => $panelDomain,
            'panel_ssl' => $dbSettings['panel_ssl'] ?? (request()->isSecure() ? '1' : '0'),
            'allow_ip_access' => $dbSettings['allow_ip_access'] ?? '1',
            'global_alert_emails' => $dbSettings['global_alert_emails'] ?? '',
        ];

        return response()->json([
            'success' => true,
            'settings' => $settings
        ]);
    }

    /**
     * Update panel settings
     */
    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'panel_name' => 'string|nullable',
            'timezone' => 'string|nullable',
            'auto_refresh' => 'boolean|nullable',
            'session_lifetime' => 'integer|nullable',
            'global_alert_emails' => 'string|nullable',
        ]);

        foreach ($validated as $key => $value) {
            if ($key === 'auto_refresh') {
                $value = $value ? '1' : '0';
            }
            Setting::updateOrCreate(['key' => $key], ['value' => (string)$value]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully'
        ]);
    }
}
