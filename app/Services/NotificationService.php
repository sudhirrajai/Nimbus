<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    /**
     * Send an email notification to the global alert recipients or Super Admins.
     * Uses the secure VMcore API first, with local Postfix / mail() fallback.
     *
     * @param string $subject
     * @param string $htmlContent
     * @return bool
     */
    public static function send(string $subject, string $htmlContent): bool
    {
        // 1. Resolve recipients: explicit global settings -> shield settings -> Super Admin / Root users
        $emails = self::resolveRecipientEmails();

        if (empty($emails)) {
            Log::warning("NotificationService: No recipient emails found for alert '{$subject}'");
            return false;
        }

        $apiUrl = 'https://vmcore.in/api/send-encrypted-email';
        $apiKey = 'vmk_ZZALOAMF78GByDGlGe3buSlly2Z32s9r7ey8KJf3w7VojizG';
        $encKey = 'UOFE3D52L3fjfCvew0rd2ed/GgwCzN521vlgJ7hmlm0=';
        $rawKey = base64_decode($encKey);
        
        $encryptValue = function($value) use ($rawKey) {
            $iv = random_bytes(16);
            $encrypted = openssl_encrypt($value, 'AES-256-CBC', $rawKey, 0, $iv);
            $mac = hash_hmac('sha256', base64_encode($iv) . $encrypted, $rawKey);

            return base64_encode(json_encode([
                'iv'    => base64_encode($iv),
                'value' => $encrypted,
                'mac'   => $mac,
                'tag'   => '',
            ]));
        };

        $allSuccess = true;

        foreach ($emails as $to) {
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $sentViaApi = false;

            // Attempt 1: VMcore Encrypted Mail API
            try {
                $payload = [
                    'to_email'          => $to,
                    'encrypted_subject' => $encryptValue($subject),
                    'encrypted_content' => $encryptValue($htmlContent),
                ];

                $ch = curl_init($apiUrl);
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER     => [
                        "X-Api-Key: $apiKey",
                        "Accept: application/json",
                        "Content-Type: application/json"
                    ],
                    CURLOPT_POSTFIELDS     => json_encode($payload),
                    CURLOPT_TIMEOUT        => 8
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlErr = curl_error($ch);
                curl_close($ch);

                if (!$curlErr && $httpCode >= 200 && $httpCode < 300) {
                    $sentViaApi = true;
                    Log::info("NotificationService: Dispatched alert '{$subject}' to {$to} via VMcore API.");
                } else {
                    Log::warning("NotificationService: VMcore API returned HTTP {$httpCode} ({$curlErr}) for {$to}. Falling back to local mailer.");
                }
            } catch (\Exception $e) {
                Log::warning("NotificationService: VMcore API exception for {$to}: " . $e->getMessage());
            }

            // Attempt 2: Fallback to local mail / Postfix
            if (!$sentViaApi) {
                try {
                    $headers = "MIME-Version: 1.0\r\n";
                    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                    $headers .= "From: Nimbus Panel <no-reply@" . (gethostname() ?: 'nimbus.local') . ">\r\n";

                    $mailSent = @mail($to, $subject, $htmlContent, $headers);
                    if ($mailSent) {
                        Log::info("NotificationService: Dispatched alert '{$subject}' to {$to} via local mailer.");
                    } else {
                        Log::error("NotificationService: Failed to deliver alert to {$to} via both API and local mailer.");
                        $allSuccess = false;
                    }
                } catch (\Exception $ex) {
                    Log::error("NotificationService: Local mail fallback failed: " . $ex->getMessage());
                    $allSuccess = false;
                }
            }
        }

        return $allSuccess;
    }

    /**
     * Resolve all recipient email addresses.
     *
     * @return array
     */
    public static function resolveRecipientEmails(): array
    {
        $emails = [];

        // Check global alert emails setting
        $alertEmails = Setting::where('key', 'global_alert_emails')->value('value');
        if (!empty(trim($alertEmails))) {
            $emails = array_merge($emails, array_map('trim', explode(',', $alertEmails)));
        }

        // Check shield alert emails setting
        $shieldEmails = Setting::where('key', 'shield_alert_emails')->value('value');
        if (!empty(trim($shieldEmails))) {
            $emails = array_merge($emails, array_map('trim', explode(',', $shieldEmails)));
        }

        // If no explicit settings configured, automatically fallback to Super Admin / Root / Admin users
        if (empty($emails)) {
            try {
                $adminUsers = User::whereIn('role', ['root', 'admin'])->pluck('email')->filter()->all();
                if (!empty($adminUsers)) {
                    $emails = array_merge($emails, $adminUsers);
                } else {
                    $firstUser = User::first();
                    if ($firstUser && !empty($firstUser->email)) {
                        $emails[] = $firstUser->email;
                    }
                }
            } catch (\Exception $e) {
                Log::warning("NotificationService: Could not load admin users from database: " . $e->getMessage());
            }
        }

        return array_values(array_unique(array_filter($emails)));
    }

    /**
     * Resolve geographical location from an IP address.
     *
     * @param string $ip
     * @return string
     */
    public static function resolveIpLocation(string $ip): string
    {
        // Check if it's a valid IP address
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return 'Unknown Location';
        }

        // Skip lookup for local/private/reserved IP ranges
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return 'Local / Private Network';
        }

        // 1. Try ip-api.com (free tier, HTTP)
        try {
            $response = Http::timeout(3)
                ->get("http://ip-api.com/json/{$ip}?fields=status,message,country,regionName,city");

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['status']) && $data['status'] === 'success') {
                    $locationParts = array_filter([
                        $data['city'] ?? null,
                        $data['regionName'] ?? null,
                        $data['country'] ?? null
                    ]);

                    if (!empty($locationParts)) {
                        return implode(', ', $locationParts);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning("IP Geolocation via ip-api.com failed for {$ip}: " . $e->getMessage());
        }

        // 2. Try ipinfo.io as fallback
        try {
            $response = Http::timeout(3)
                ->get("https://ipinfo.io/{$ip}/json");

            if ($response->successful()) {
                $data = $response->json();
                $locationParts = array_filter([
                    $data['city'] ?? null,
                    $data['region'] ?? null,
                    $data['country'] ?? null
                ]);

                if (!empty($locationParts)) {
                    return implode(', ', $locationParts);
                }
            }
        } catch (\Exception $e) {
            Log::warning("IP Geolocation via ipinfo.io failed for {$ip}: " . $e->getMessage());
        }

        return 'Unknown Location';
    }
}
