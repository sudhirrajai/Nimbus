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
        $user = Auth::user();
        return Inertia::render('Profile/Index', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'has_2fa' => $user->hasTwoFactorEnabled(),
                'two_factor_confirmed_at' => $user->two_factor_confirmed_at?->toDateTimeString(),
            ]
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
     * Start 2FA setup — generate secret and QR code SVG
     */
    public function setupTwoFactor(\App\Services\TwoFactorService $twoFactorService)
    {
        $user = Auth::user();
        $secret = $twoFactorService->generateSecretKey();
        $qrSvg = $twoFactorService->getQrCodeSvg($user, $secret);

        return response()->json([
            'success' => true,
            'secret' => $secret,
            'qr_svg' => $qrSvg,
        ]);
    }

    /**
     * Confirm 2FA setup with user-supplied TOTP code
     */
    public function confirmTwoFactor(Request $request, \App\Services\TwoFactorService $twoFactorService)
    {
        $request->validate([
            'secret' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        $user = Auth::user();
        $result = $twoFactorService->confirmTwoFactor($user, $request->secret, $request->code);

        if (!$result['status']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        \App\Models\ActivityLog::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'action' => 'security_2fa_enabled',
            'service' => 'auth',
            'description' => 'Enabled two-factor authentication',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'recovery_codes' => $result['recovery_codes'],
        ]);
    }

    /**
     * Disable 2FA with current password confirmation
     */
    public function disableTwoFactor(Request $request, \App\Services\TwoFactorService $twoFactorService)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'The provided password was incorrect.',
            ], 422);
        }

        $twoFactorService->disableTwoFactor($user);

        \App\Models\ActivityLog::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'action' => 'security_2fa_disabled',
            'service' => 'auth',
            'description' => 'Disabled two-factor authentication',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Two-factor authentication has been disabled.',
        ]);
    }

    /**
     * Regenerate 2FA recovery codes
     */
    public function regenerateRecoveryCodes(Request $request, \App\Services\TwoFactorService $twoFactorService)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = Auth::user();

        if (!$user->hasTwoFactorEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'Two-factor authentication is not enabled.',
            ], 400);
        }

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'The provided password was incorrect.',
            ], 422);
        }

        $codes = $twoFactorService->generateRecoveryCodes();
        $user->two_factor_recovery_codes = $codes;
        $user->save();

        return response()->json([
            'success' => true,
            'recovery_codes' => $codes,
        ]);
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
        if (empty($panelDomain) && !filter_var(request()->getHost(), FILTER_VALIDATE_IP)) {
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

        $users = User::select('id', 'name', 'email')->get();

        return response()->json([
            'success' => true,
            'settings' => $settings,
            'users' => $users
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
