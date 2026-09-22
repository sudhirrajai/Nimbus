<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use App\Models\UserWebsite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SsoController extends Controller
{
    /**
     * Handle single sign-on from VmCoreCentral dashboard.
     */
    public function login(Request $request)
    {
        $token = $request->query('token');
        if (empty($token) || !str_contains($token, '.')) {
            return redirect()->route('auth.login')->withErrors(['error' => 'Invalid or missing SSO authorization token.']);
        }

        [$payloadB64, $signature] = explode('.', $token, 2);

        // Resolve shared secret
        $secret = config('services.nimbus.sso_secret');
        if (empty($secret)) {
            $secret = env('NIMBUS_SSO_SECRET');
        }
        if (empty($secret)) {
            $secret = Setting::where('key', 'nimbus_sso_secret')->orWhere('key', 'sso_secret')->value('value');
        }

        if (empty($secret)) {
            Log::error('SSO Login attempted but no NIMBUS_SSO_SECRET is configured on this server.');
            return redirect()->route('auth.login')->withErrors(['error' => 'SSO service is not configured on this Nimbus node.']);
        }

        // Verify HMAC-SHA256 signature
        $expectedSignature = hash_hmac('sha256', $payloadB64, $secret);
        if (!hash_equals($expectedSignature, $signature)) {
            Log::warning('SSO signature verification failed. Token was tampered with or secret mismatch.');
            return redirect()->route('auth.login')->withErrors(['error' => 'SSO signature validation failed. Access denied.']);
        }

        // Decode JSON payload
        $json = base64_decode($payloadB64);
        $payload = json_decode($json, true);

        if (!is_array($payload) || empty($payload['user_id']) || empty($payload['timestamp']) || empty($payload['nonce'])) {
            return redirect()->route('auth.login')->withErrors(['error' => 'Malformed SSO token payload.']);
        }

        // Check timestamp freshness (allow max 2 minutes skew)
        if (abs(time() - $payload['timestamp']) > 120) {
            return redirect()->route('auth.login')->withErrors(['error' => 'SSO session token has expired. Please launch again from VmCoreCentral.']);
        }

        // Check nonce to prevent replay attacks
        $nonceKey = 'sso_nonce:' . $payload['nonce'];
        if (Cache::has($nonceKey)) {
            return redirect()->route('auth.login')->withErrors(['error' => 'This SSO token has already been used. Please launch again from VmCoreCentral.']);
        }
        Cache::put($nonceKey, 1, 300); // 5 minutes retention

        // Authenticate user according to role
        $role = $payload['role'] ?? 'client';

        if ($role === 'admin') {
            // Super Admin SSO
            $adminUser = User::where('role', 'root')->first()
                ?? User::where('role', 'admin')->first()
                ?? User::first();

            if (!$adminUser) {
                return redirect()->route('auth.login')->withErrors(['error' => 'No administrator user account found on this panel.']);
            }

            Auth::login($adminUser, true);
            $request->session()->regenerate();

            Log::info("SSO: Admin user '{$adminUser->email}' logged in via VmCoreCentral SSO.");
            return redirect()->route('dashboard');
        }

        // Client SSO (Dedicated domain/account)
        $email = $payload['email'] ?? null;
        if (!$email) {
            return redirect()->route('auth.login')->withErrors(['error' => 'Client email missing in SSO payload.']);
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            // Create user account for managed client
            $user = User::create([
                'name' => $payload['name'] ?? explode('@', $email)[0],
                'email' => $email,
                'password' => bcrypt(Str::random(32)),
                'role' => 'user',
                'status' => 'active',
                'file_manager_scope' => 'domain',
            ]);
        }

        // Ensure user has website assignment if a domain was specified
        if (!empty($payload['domain'])) {
            $targetDomain = strtolower(trim($payload['domain']));
            $hasWebsite = $user->websites()->whereRaw('LOWER(domain) = ?', [$targetDomain])->exists();
            if (!$hasWebsite) {
                $user->websites()->create([
                    'domain' => $targetDomain,
                    'permissions' => ['files', 'wordpress', 'database', 'ssl', 'nginx', 'supervisor', 'cron', 'dns', 'backups'],
                ]);
            }
        }

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        Auth::login($user, true);
        $request->session()->regenerate();

        Log::info("SSO: Managed client user '{$user->email}' logged in via VmCoreCentral SSO.");

        return redirect()->route('dashboard');
    }
}
