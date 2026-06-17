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
    public function showLogin()
    {
        // If no users exist, redirect to setup
        if (User::count() === 0) {
            return redirect()->route('auth.setup');
        }

        return Inertia::render('Auth/Login');
    }

    /**
     * Handle login
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::user();

            // Clear license cache on login to force fresh check
            app(\App\Services\LicenseService::class)->clearCache();

            // System integrity check
            if (!\App\Support\LicenseGuard::ok()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect()->route('activate.index')->with('error', 'System integrity check failed.');
            }

            // Block suspended users
            if ($user->status === 'suspended') {
                Auth::logout();
                return back()->withErrors([
                    'email' => 'Your account has been suspended. Contact the administrator.',
                ])->onlyInput('email');
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

            $request->session()->regenerate();
            return redirect()->intended('/dashboard');
        }

        // Send alert for failed login
        $ip = $request->ip();
        $location = \App\Services\NotificationService::resolveIpLocation($ip);

        \App\Services\NotificationService::send(
            "Security Alert: Failed Login Attempt",
            "<p>Hello,</p><p>A failed login attempt was detected on the Nimbus panel.</p><p><strong>Attempted Email:</strong> " . e($request->input('email')) . "<br><strong>IP Address:</strong> " . e($ip) . " (" . e($location) . ")<br><strong>Time:</strong> " . now()->toDateTimeString() . "</p>"
        );

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Handle logout
     */
    public function logout(Request $request)
    {
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

        Auth::login($user);

        // Clear license cache on setup to force fresh check
        app(\App\Services\LicenseService::class)->clearCache();

        return redirect()->route('dashboard');
    }
}
