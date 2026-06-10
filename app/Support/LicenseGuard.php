<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * System integrity verification helper.
 *
 * Performs fast, offline verification of the license token's RSA signature
 * using the hardcoded public key. Designed to be embedded as one-line calls
 * inside critical controllers as a secondary enforcement layer.
 *
 * The class name and method names are deliberately non-obvious to make it
 * harder to locate via simple code searches for "license".
 */
class LicenseGuard
{
    /** Cache the result per-request to avoid repeated DB/crypto calls */
    private static ?bool $cached = null;

    /** Timestamp of when degradation was first triggered */
    private static ?string $degradedAt = null;

    /**
     * Quick integrity check — returns true if the system is operational.
     * Designed for embedding in controller actions as:
     *   if (!\App\Support\LicenseGuard::ok()) { ... }
     */
    public static function ok(): bool
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        try {
            // Check 1: Is the panel already in degraded state?
            $locked = DB::table('settings')
                ->where('key', 'license_is_locked')
                ->value('value');

            if ($locked === '1') {
                self::$cached = false;
                return false;
            }

            // Check 2: Is there a valid signed token?
            $token = DB::table('settings')
                ->where('key', 'license_token')
                ->value('value');

            if (!$token) {
                self::$cached = false;
                self::flag('No verification token present');
                return false;
            }

            // Check 3: Verify the RSA signature
            $service = app(\App\Services\LicenseService::class);
            $decoded = $service->verifySignedToken($token);

            if (!$decoded) {
                self::$cached = false;
                self::flag('Verification token signature mismatch');
                return false;
            }

            // Token is cryptographically valid
            self::$cached = true;
            return true;

        } catch (\Exception $e) {
            // If DB is down or during migration, don't block
            return true;
        }
    }

    /**
     * Get the number of hours since degradation started.
     * Returns 0 if not degraded.
     */
    public static function hoursSinceDegraded(): float
    {
        try {
            if (self::$degradedAt === null) {
                self::$degradedAt = DB::table('settings')
                    ->where('key', 'license_degraded_at')
                    ->value('value') ?? '';
            }

            if (empty(self::$degradedAt)) {
                return 0;
            }

            $degradedTime = strtotime(self::$degradedAt);
            return max(0, (time() - $degradedTime) / 3600);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Determine if a specific feature should be blocked based on degradation timeline.
     * 
     * Silent degradation timeline:
     *  - 0-6 hours:   Only dashboard warnings, all features work
     *  - 6-24 hours:  Block NEW domain/database/cron/SSL creation
     *  - 24+ hours:   Block most write operations
     *
     * @param string $tier  'create' (6h threshold) or 'critical' (24h threshold)
     */
    public static function isBlocked(string $tier = 'create'): bool
    {
        if (self::ok()) {
            return false;
        }

        $hours = self::hoursSinceDegraded();

        return match ($tier) {
            'create'   => $hours >= 6,   // Block new resource creation after 6 hours
            'critical' => $hours >= 24,  // Block critical operations after 24 hours
            default    => $hours >= 6,
        };
    }

    /**
     * Get a vague, non-license-related error message for silent degradation.
     * These messages are intentionally misleading to prevent users from
     * correlating the error to the license check.
     */
    public static function degradedMessage(string $context = 'default'): string
    {
        $messages = [
            'domain'   => 'Unable to allocate system resources for this operation. Please try again later or contact support.',
            'database' => 'Database provisioning service is temporarily unavailable. Please try again later.',
            'cron'     => 'Task scheduler is experiencing resource constraints. Please try again later.',
            'ssl'      => 'Certificate provisioning service returned an error. Please try again later.',
            'nginx'    => 'Configuration service encountered an internal error. Please try again later.',
            'default'  => 'This operation is temporarily unavailable. Please try again later or contact support.',
        ];

        return $messages[$context] ?? $messages['default'];
    }

    /**
     * Check if a dashboard warning should be shown (any degradation state).
     */
    public static function shouldShowWarning(): bool
    {
        return !self::ok() && self::hoursSinceDegraded() > 0;
    }

    /**
     * Get the dashboard warning message.
     */
    public static function warningMessage(): string
    {
        $hours = self::hoursSinceDegraded();

        if ($hours < 6) {
            return 'System health check warning — some features may be affected. Please verify your system configuration.';
        } elseif ($hours < 24) {
            return 'System integrity issue detected — resource creation is temporarily restricted. Contact support for assistance.';
        } else {
            return 'Critical system issue detected — most operations are restricted. Immediate attention required. Contact support.';
        }
    }

    /**
     * Flag the system for degradation.
     */
    private static function flag(string $reason): void
    {
        try {
            DB::table('settings')->updateOrInsert(
                ['key' => 'license_is_locked'],
                ['value' => '1', 'updated_at' => now()]
            );
            DB::table('settings')->updateOrInsert(
                ['key' => 'license_lock_reason'],
                ['value' => $reason, 'updated_at' => now()]
            );
            if (!DB::table('settings')->where('key', 'license_degraded_at')->exists()) {
                DB::table('settings')->insert([
                    'key' => 'license_degraded_at',
                    'value' => now()->toIso8601String(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            // Silently fail if DB isn't available
        }
    }

    /**
     * Reset per-request cache (for testing).
     */
    public static function reset(): void
    {
        self::$cached = null;
        self::$degradedAt = null;
    }
}
