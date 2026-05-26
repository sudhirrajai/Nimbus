<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send an encrypted email notification to the global alert recipients.
     *
     * @param string $subject
     * @param string $htmlContent
     * @return bool
     */
    public static function send(string $subject, string $htmlContent): bool
    {
        $alertEmails = Setting::where('key', 'global_alert_emails')->value('value');
        if (empty(trim($alertEmails))) {
            // Fallback to shield_alert_emails if global isn't set yet
            $alertEmails = Setting::where('key', 'shield_alert_emails')->value('value');
            if (empty(trim($alertEmails))) {
                return false;
            }
        }

        $emails = array_filter(array_map('trim', explode(',', $alertEmails)));
        
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

        $success = true;

        foreach ($emails as $to) {
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

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
                CURLOPT_TIMEOUT        => 10
            ]);

            $response = curl_exec($ch);
            
            if (curl_errno($ch)) {
                Log::error("Failed to send encrypted email to $to: " . curl_error($ch));
                $success = false;
            }
            
            curl_close($ch);
        }

        return $success;
    }
}
