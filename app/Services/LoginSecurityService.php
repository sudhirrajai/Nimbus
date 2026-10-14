<?php

namespace App\Services;

use App\Models\SecurityRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginSecurityService
{
    const MAX_THROTTLE_ATTEMPTS = 5;       // Attempts before 60-second cooldown
    const THROTTLE_DECAY_SECONDS = 60;     // 60-second cooldown duration
    const CAPTCHA_THRESHOLD = 2;           // Failed attempts before requiring captcha
    const HARD_BAN_THRESHOLD = 10;         // Failed attempts before 15-minute temporary IP ban
    const HARD_BAN_DURATION_SECONDS = 900; // 15 minutes (900 seconds)

    /**
     * Check if the incoming IP is temporarily banned due to excessive brute-force attempts.
     * Returns remaining seconds or null if clean.
     */
    public function getIpBanSecondsRemaining(string $ip): ?int
    {
        $banExpiresAt = Cache::get('login_banned_ip_' . $ip);
        if ($banExpiresAt && $banExpiresAt > time()) {
            return $banExpiresAt - time();
        }
        return null;
    }

    /**
     * Check if request is currently throttled (5 attempts in 1 min).
     * Returns remaining seconds or null if clean.
     */
    public function getThrottleSecondsRemaining(Request $request): ?int
    {
        $key = $this->throttleKey($request);
        if (RateLimiter::tooManyAttempts($key, self::MAX_THROTTLE_ATTEMPTS)) {
            return RateLimiter::availableIn($key);
        }
        return null;
    }

    /**
     * Determine if a captcha is required for this request.
     */
    public function requiresCaptcha(Request $request): bool
    {
        $ip = $request->ip();
        $failCount = (int) Cache::get('login_fail_count_' . $ip, 0);
        return $failCount >= self::CAPTCHA_THRESHOLD;
    }

    /**
     * Generate an inline SVG math challenge captcha.
     */
    public function generateCaptcha(Request $request): array
    {
        $a = rand(3, 18);
        $b = rand(1, 9);
        $answer = $a + $b;

        $request->session()->put('login_captcha_answer', (string) $answer);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="130" height="38" viewBox="0 0 130 38">' .
               '<rect width="130" height="38" rx="8" fill="#181920" stroke="rgba(255,255,255,0.15)" stroke-width="1"/>' .
               '<text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" font-family="-apple-system, sans-serif" font-weight="700" font-size="16" fill="#eb1466" letter-spacing="2">' .
               "{$a} + {$b} = ?" .
               '</text></svg>';

        return [
            'required' => true,
            'image' => 'data:image/svg+xml;base64,' . base64_encode($svg),
        ];
    }

    /**
     * Verify the user-submitted captcha answer.
     */
    public function verifyCaptcha(Request $request): bool
    {
        $expected = $request->session()->get('login_captcha_answer');
        $given = trim((string) $request->input('captcha_answer'));

        if (empty($expected) || $given !== (string) $expected) {
            return false;
        }

        $request->session()->forget('login_captcha_answer');
        return true;
    }

    /**
     * Record a failed login attempt.
     */
    public function recordFailedAttempt(Request $request): array
    {
        $ip = $request->ip();
        $key = $this->throttleKey($request);

        RateLimiter::hit($key, self::THROTTLE_DECAY_SECONDS);

        $failKey = 'login_fail_count_' . $ip;
        $failCount = (int) Cache::get($failKey, 0) + 1;
        Cache::put($failKey, $failCount, 3600); // Track 1-hour window

        $banned = false;
        if ($failCount >= self::HARD_BAN_THRESHOLD) {
            Cache::put('login_banned_ip_' . $ip, time() + self::HARD_BAN_DURATION_SECONDS, self::HARD_BAN_DURATION_SECONDS);
            $banned = true;
        }

        return [
            'count' => $failCount,
            'requires_captcha' => $failCount >= self::CAPTCHA_THRESHOLD,
            'banned' => $banned,
            'ban_seconds' => $banned ? self::HARD_BAN_DURATION_SECONDS : 0,
        ];
    }

    /**
     * Reset login failure counters on successful login.
     */
    public function recordSuccessfulLogin(Request $request): void
    {
        $ip = $request->ip();
        $key = $this->throttleKey($request);

        RateLimiter::clear($key);
        Cache::forget('login_fail_count_' . $ip);
        Cache::forget('login_banned_ip_' . $ip);
        $request->session()->forget('login_captcha_answer');
    }

    /**
     * Unban an IP address and clear all rate limit counters.
     */
    public function unbanIp(string $ip): void
    {
        Cache::forget('login_banned_ip_' . $ip);
        Cache::forget('login_fail_count_' . $ip);

        // Also remove from security_rules blacklist if present
        SecurityRule::where('ip_address', $ip)->where('type', 'block')->delete();
    }

    /**
     * Generate composite throttle key for RateLimiter.
     */
    protected function throttleKey(Request $request): string
    {
        $email = Str::transliterate(Str::lower((string) $request->input('email')));
        return "login_throttle:{$email}|{$request->ip()}";
    }
}
