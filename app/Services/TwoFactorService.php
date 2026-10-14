<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Generate a new Base32 secret key.
     */
    public function generateSecretKey(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    /**
     * Get the OTP auth URL.
     */
    public function getQrCodeUrl(User $user, string $secret): string
    {
        $appName = config('app.name', 'Nimbus Control Panel');
        return $this->google2fa->getQRCodeUrl($appName, $user->email, $secret);
    }

    /**
     * Generate inline SVG QR code.
     */
    public function getQrCodeSvg(User $user, string $secret): string
    {
        $url = $this->getQrCodeUrl($user, $secret);

        $renderer = new ImageRenderer(
            new RendererStyle(220, 1),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        return $writer->writeString($url);
    }

    /**
     * Verify a 6-digit TOTP code against a secret key.
     */
    public function verifyCode(string $secret, string $code): bool
    {
        $code = trim($code);
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            return false;
        }

        // Window = 1 allows 1 slice (30s) before and 1 slice after to handle minor clock drift
        return (bool) $this->google2fa->verifyKey($secret, $code, 1);
    }

    /**
     * Generate emergency recovery codes.
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(Str::random(5) . '-' . Str::random(5));
        }
        return $codes;
    }

    /**
     * Verify and consume a recovery code.
     */
    public function verifyAndConsumeRecoveryCode(User $user, string $code): bool
    {
        $code = strtoupper(trim($code));
        $codes = $user->two_factor_recovery_codes ?? [];

        if (!is_array($codes) || empty($codes)) {
            return false;
        }

        $index = array_search($code, $codes, true);
        if ($index === false) {
            return false;
        }

        // Remove the used recovery code
        unset($codes[$index]);
        $user->two_factor_recovery_codes = array_values($codes);
        $user->save();

        return true;
    }

    /**
     * Enable 2FA for a user once verified.
     */
    public function confirmTwoFactor(User $user, string $secret, string $code): array
    {
        if (!$this->verifyCode($secret, $code)) {
            return [
                'status' => false,
                'message' => 'The provided two-factor authentication code is invalid.',
            ];
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $recoveryCodes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        return [
            'status' => true,
            'recovery_codes' => $recoveryCodes,
            'message' => 'Two-factor authentication has been successfully enabled.',
        ];
    }

    /**
     * Disable 2FA for a user.
     */
    public function disableTwoFactor(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }
}
