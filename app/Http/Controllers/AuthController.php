<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class AuthController extends Controller
{
    /**
     * Show login page
     */
    public function showLogin(Request $request, \App\Services\LoginSecurityService $securityService)
    {
        // If no users exist, redirect to setup
        if (User::count() === 0) {
            return redirect()->route('auth.setup');
        }

        if ($securityService->getIpBanSecondsRemaining($request->ip())) {
            return response()->view('errors.ip-blocked', ['ip' => $request->ip()], 429);
        }

        $captcha = null;
        if ($securityService->requiresCaptcha($request)) {
            $captcha = $securityService->generateCaptcha($request);
        }

        return Inertia::render('Auth/Login', [
            'captcha' => $captcha,
        ]);
    }

    /**
     * Handle login
     */
    public function login(Request $request, \App\Services\LoginSecurityService $securityService)
    {
        // Check temporary brute force ban (10+ failures)
        if ($seconds = $securityService->getIpBanSecondsRemaining($request->ip())) {
            $mins = max(1, (int) ceil($seconds / 60));
            return back()->withErrors([
                'email' => "Your IP has been temporarily locked out due to multiple failed login attempts. Please try again in {$mins} minute(s)."
            ]);
        }

        // Check throttle soft-lock (5 attempts in 1 min)
        if ($seconds = $securityService->getThrottleSecondsRemaining($request)) {
            return back()->withErrors([
                'email' => "Too many login attempts. Please wait {$seconds} seconds before trying again."
            ]);
        }

        // If captcha is required, verify it
        if ($securityService->requiresCaptcha($request)) {
            $request->validate([
                'captcha_answer' => 'required',
            ], [
                'captcha_answer.required' => 'Please solve the security challenge.',
            ]);

            if (!$securityService->verifyCaptcha($request)) {
                $securityService->recordFailedAttempt($request);
                return back()->withErrors([
                    'captcha_answer' => 'Incorrect answer. Please solve the security puzzle.',
                ])->with('captcha', $securityService->generateCaptcha($request));
            }
        }

        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::validate($credentials)) {
            $user = User::where('email', $credentials['email'])->first();

            // Block suspended users
            if ($user->status === 'suspended') {
                return back()->withErrors([
                    'email' => 'Your account has been suspended. Contact the administrator.',
                ])->onlyInput('email');
            }

            // Check system integrity
            if (!\App\Support\LicenseGuard::ok()) {
                return redirect()->route('activate.index')->with('error', 'System integrity check failed.');
            }

            // Reset rate limiter on valid credentials
            $securityService->recordSuccessfulLogin($request);

            // If user has 2FA enabled, challenge them
            if ($user->hasTwoFactorEnabled()) {
                $request->session()->put('login.id', $user->id);
                $request->session()->put('login.remember', $request->boolean('remember'));

                return redirect()->route('two-factor.login');
            }

            return $this->completeLogin($user, $request, $request->boolean('remember'));
        }

        // Record failed attempt in rate limiter and tracking cache
        $result = $securityService->recordFailedAttempt($request);

        // Send alert for failed login
        $ip = $request->ip();
        $location = \App\Services\NotificationService::resolveIpLocation($ip);

        \App\Services\NotificationService::send(
            "Security Alert: Failed Login Attempt",
            "<p>Hello,</p><p>A failed login attempt was detected on the Nimbus panel.</p><p><strong>Attempted Email:</strong> " . e($request->input('email')) . "<br><strong>IP Address:</strong> " . e($ip) . " (" . e($location) . ")<br><strong>Time:</strong> " . now()->toDateTimeString() . "</p>"
        );

        // Log failed login
        \App\Models\ActivityLog::create([
            'user_id' => null,
            'email' => $request->input('email'),
            'action' => 'failed_login',
            'service' => 'auth',
            'description' => 'Failed login attempt',
            'ip_address' => $ip,
            'user_agent' => $request->userAgent(),
        ]);

        $errorMessage = 'The provided credentials do not match our records.';
        if (!empty($result['banned'])) {
            $errorMessage = 'Too many failed attempts. Your IP has been temporarily locked out for 15 minutes.';
        } elseif (!empty($result['requires_captcha'])) {
            $errorMessage .= ' Please solve the security verification challenge to continue.';
        }

        return back()->withErrors([
            'email' => $errorMessage,
        ])->onlyInput('email');
    }

    /**
     * Show two-factor challenge screen
     */
    public function showTwoFactorChallenge(Request $request)
    {
        if (!$request->session()->has('login.id')) {
            return redirect()->route('auth.login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    /**
     * Verify two-factor challenge (via TOTP code or recovery code)
     */
    public function twoFactorLogin(Request $request, \App\Services\TwoFactorService $twoFactorService)
    {
        $userId = $request->session()->get('login.id');
        if (!$userId) {
            return redirect()->route('auth.login');
        }

        $user = User::find($userId);
        if (!$user || !$user->hasTwoFactorEnabled()) {
            $request->session()->forget(['login.id', 'login.remember']);
            return redirect()->route('auth.login');
        }

        $request->validate([
            'code' => 'nullable|string',
            'recovery_code' => 'nullable|string',
        ]);

        $remember = (bool) $request->session()->get('login.remember', false);
        $authenticated = false;

        if ($request->filled('code')) {
            $authenticated = $twoFactorService->verifyCode($user->two_factor_secret, $request->code);
        } elseif ($request->filled('recovery_code')) {
            $authenticated = $twoFactorService->verifyAndConsumeRecoveryCode($user, $request->recovery_code);
        }

        if (!$authenticated) {
            \App\Models\ActivityLog::create([
                'user_id' => $user->id,
                'email' => $user->email,
                'action' => 'failed_2fa',
                'service' => 'auth',
                'description' => 'Failed 2FA verification attempt',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors([
                'code' => 'The provided authentication code or recovery code is invalid.',
            ]);
        }

        $request->session()->forget(['login.id', 'login.remember']);

        return $this->completeLogin($user, $request, $remember);
    }

    /**
     * Complete login ceremony after credentials & 2FA pass
     */
    protected function completeLogin(User $user, Request $request, bool $remember = false)
    {
        Auth::login($user, $remember);

        // Clear license cache on login to force fresh check
        app(\App\Services\LicenseService::class)->clearCache();

        // System integrity check
        if (!\App\Support\LicenseGuard::ok()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('activate.index')->with('error', 'System integrity check failed.');
        }

        $currentIp = $request->ip();
        $oldIp = $user->last_login_ip;

        if ($oldIp && $oldIp !== $currentIp) {
            $newLocation = \App\Services\NotificationService::resolveIpLocation($currentIp);
            $oldLocation = \App\Services\NotificationService::resolveIpLocation($oldIp);

            \App\Services\NotificationService::send(
                "Security Alert: Successful Login from New IP",
                "<p>Hello,</p><p>A successful login to the Nimbus panel was detected from a new IP address.</p><p><strong>User:</strong> " . e($user->name) . " (" . e($user->email) . ")<br><strong>New IP Address:</strong> " . e($currentIp) . " (" . e($newLocation) . ")<br><strong>Previous IP Address:</strong> " . e($oldIp) . " (" . e($oldLocation) . ")<br><strong>Time:</strong> " . now()->toDateTimeString() . "</p><p>If this wasn't you, please secure your account immediately.</p>"
            );
        }

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $currentIp,
        ]);

        // Log successful login
        \App\Models\ActivityLog::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'action' => 'login',
            'service' => 'auth',
            'description' => 'Successfully logged in' . ($user->hasTwoFactorEnabled() ? ' (2FA verified)' : ''),
            'ip_address' => $currentIp,
            'user_agent' => $request->userAgent(),
        ]);

        $request->session()->regenerate();
        return redirect()->intended('/dashboard');
    }

    /**
     * Handle logout
     */
    public function logout(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            \App\Models\ActivityLog::create([
                'user_id' => $user->id,
                'email' => $user->email,
                'action' => 'logout',
                'service' => 'auth',
                'description' => 'Logged out',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('auth.login');
    }

    /**
     * Show setup page (first-time setup)
     */
    public function showSetup()
    {
        // If users already exist, redirect to login
        if (User::count() > 0) {
            return redirect()->route('auth.login');
        }

        return Inertia::render('Auth/Setup');
    }

    /**
     * Handle first-time setup
     */
    public function setup(Request $request)
    {
        // Prevent if users already exist
        if (User::count() > 0) {
            return redirect()->route('auth.login');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'root',
        ]);

        // Log setup completion
        \App\Models\ActivityLog::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'action' => 'setup',
            'service' => 'auth',
            'description' => 'Completed initial system setup',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        Auth::login($user);

        // Clear license cache on setup to force fresh check
        app(\App\Services\LicenseService::class)->clearCache();

        return redirect()->route('dashboard');
    }
}
