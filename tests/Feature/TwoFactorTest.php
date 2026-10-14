<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use App\Services\LicenseService;
use App\Support\LicenseGuard;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_PRIVATE_KEY = '-----BEGIN PRIVATE KEY-----
MIIEvAIBADANBgkqhkiG9w0BAQEFAASCBKYwggSiAgEAAoIBAQDVaLZI2Bpo6SHq
LphpGOdBEbeZNB4xXDRpxTI0CUvYkluw4TJNPrfmpVkAkhTijrIqsawQyAPEcppD
gil8aqpaUMlWgNLwYeDkT/gZLXZaMqYAMfD4ljAvCXPxCs1RujQpxZw/vF2rAoZV
bOD0B7T369DnZlb7DlSrhBf5DVu+bFs6aSM4k2ugDMdNWAucJwiMRoLXeQsqTvXF
T0O5EZHN005TVmiBO2PB1iwwaTqBAtNf4ZP3H+klCei3yup73kfcASFptsNL2C4K
v1HIj0qSTQRk8JHx5hxhFdc9CsF29jHGl+bkvNmviz41/Cl4V7ORlkI9S20NSNf0
t86nLnD/AgMBAAECggEABiZIzmuPw0Moi4eBM9IYY9sfV8ZVMmXuSrf4fOBhs6Fw
C/ZJTUmH4hkUqfwLpwWmo61QAIHK/f+xa02WZXPracPXMWtv6KZhZSLaytrxBKNd
/jZyM6+cPta+ZQ1DIl5Dv4pmuL10U4e0mESVbOLF97jvYaOk0QI2NWUOjcoxQRjv
wPIGgv3UkXaa8q4fpj1cAxB/H+4ug0E0fvZThJsb+BJxywVF+4KFz0BPvrOyXiK/
euN8mYlAUbDEWAebbJksS6TF+1SIQKjxWiIOu4t1kdszwGD31JfR7mjF/7CthLgQ
WGbk+8cDbWGbUbh40B55rQ8ahumEPk+Xid7/gmfQOQKBgQD2GHe5Csv0M3c01OKm
EhnsZG+HVtq0PJymT/HQMBBgg2cDNvrCt5AN/d5PU5ntE9O9nSdBmna07Wy7TEUl
oHne/6ccPMCVA/e4ThGG/R5TNcFQnCajXR1W+4gqv9fS4fqW8hBrj66dPNhLIsq6
NR+mlAGuyQFi38+KGu6FTHvSpwKBgQDd/3k/v0Dq8mxxSb/XOPybDC9XFM8Ug5fp
FwqPiXY8cxoYntKqoSMOfjUimt55tWuYfLeLXFeeOyB1xHRsg3hZ1rd4UUoDAw4S
xRh+dkkHczb2383EWMedlFxX5/6CwpYy/3fogBhxZb+qKyW0URMSRctls5atJBMu
nBpMWYBx6QKBgGwnQKaACjZeT+tWC+20UtRDJ4ixMRi48pdc1wcJuIjR9vnAtd/R
UGv1wDfYo/M/HXVdlZ0NR1Iobfq6gEETD7xjWovcXz6eKcZD+Qv8PdGP9E968Lgm
+ff6P1OaUD2LtteTtoeu86yCywPqXHINWsWYkkzAZe2QMuOdBmpqvJhNAoGAfjW/
LjHCa5B1fJbMHUUFv/RWebCX5nbGB7uUwnQJJ2bc4EBzTpSbxKKV/N2FPDabPC8z
fmR6X7gHxUxyUDsSUikTV7EHXdz/xEYPnd4LuNOU3Rfx+P3sRrdRJJz9gkO0drvs
5N7mhpmtNMahAfnR2OKbN2+5aygGS8pt3RhJQPkCgYBqBcFViYmVGEhup5M2z+3b
04n+BzdR7Gy7ouzzeYNUFY/qkQb0aDzzTlMMiB5GUMSgdcJUQEsWml6ME6+o5/cM
xoJPfOXrpezVIuC9vnqv4ZXArc2ROiPJ0hjGinYbvXzhzLL/WF13E3Rm+L8CmMmP
rtmnnObzqUeWMPffTvN1IQ==
-----END PRIVATE KEY-----';

    private function generateToken(array $payloadData): string
    {
        $payload = json_encode($payloadData);
        $privateKey = openssl_pkey_get_private(self::TEST_PRIVATE_KEY);
        openssl_sign($payload, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=') . '.' . rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    protected function setUp(): void
    {
        parent::setUp();

        LicenseGuard::reset();

        $token = $this->generateToken([
            'license_key' => 'NIMB-1234-5678-ABCD',
            'machine_id'  => app(LicenseService::class)->getMachineId(),
            'plan'        => 'pro',
            'max_domains' => 10,
            'valid_until' => date('Y-m-d H:i:s', time() + 3600),
        ]);

        DB::table('settings')->insert([
            ['key' => 'license_key', 'value' => 'NIMB-1234-5678-ABCD', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'license_token', 'value' => $token, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'last_successful_license_check', 'value' => date('c'), 'created_at' => now(), 'updated_at' => now()]
        ]);

        LicenseGuard::reset();
    }

    public function test_user_can_initialize_2fa_setup(): void
    {
        $user = User::factory()->create([
            'role' => 'root',
        ]);

        $response = $this->actingAs($user)->postJson('/profile/2fa/setup');

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'secret', 'qr_svg']);
        $this->assertNotEmpty($response->json('secret'));
        $this->assertStringContainsString('<svg', $response->json('qr_svg'));
    }

    public function test_user_can_confirm_and_enable_2fa(): void
    {
        $user = User::factory()->create([
            'role' => 'root',
        ]);

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();
        $code = $google2fa->getCurrentOtp($secret);

        $response = $this->actingAs($user)->postJson('/profile/2fa/confirm', [
            'secret' => $secret,
            'code' => $code,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertCount(8, $response->json('recovery_codes'));

        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertCount(8, $user->two_factor_recovery_codes);
    }

    public function test_cannot_confirm_2fa_with_invalid_code(): void
    {
        $user = User::factory()->create();

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $response = $this->actingAs($user)->postJson('/profile/2fa/confirm', [
            'secret' => $secret,
            'code' => '000000',
        ]);

        $response->assertStatus(422);
        $user->refresh();
        $this->assertFalse($user->hasTwoFactorEnabled());
    }

    public function test_user_with_2fa_is_redirected_to_challenge_on_login(): void
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'email' => 'admin2fa@nimbus.local',
            'password' => Hash::make('password123'),
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => ['TEST-CODE-1', 'TEST-CODE-2'],
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->post('/login', [
            'email' => 'admin2fa@nimbus.local',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
        $response->assertSessionHas('login.id', $user->id);
    }

    public function test_user_can_verify_2fa_and_complete_login(): void
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();
        $code = $google2fa->getCurrentOtp($secret);

        $user = User::factory()->create([
            'email' => 'admin2fa@nimbus.local',
            'password' => Hash::make('password123'),
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => ['TEST-CODE-1'],
            'two_factor_confirmed_at' => now(),
        ]);

        $this->withSession(['login.id' => $user->id]);

        $response = $this->post('/two-factor-challenge', [
            'code' => $code,
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_login_with_recovery_code_and_consumes_it(): void
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'email' => 'admin2fa@nimbus.local',
            'password' => Hash::make('password123'),
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => ['RECOVERY-CODE-1', 'RECOVERY-CODE-2'],
            'two_factor_confirmed_at' => now(),
        ]);

        $this->withSession(['login.id' => $user->id]);

        $response = $this->post('/two-factor-challenge', [
            'recovery_code' => 'RECOVERY-CODE-1',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertEquals(['RECOVERY-CODE-2'], $user->two_factor_recovery_codes);
    }

    public function test_user_can_disable_2fa_with_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('mypassword123'),
            'two_factor_secret' => 'SECRETKEY',
            'two_factor_recovery_codes' => ['CODE1'],
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson('/profile/2fa/disable', [
            'password' => 'mypassword123',
        ]);

        $response->assertStatus(200);
        $user->refresh();
        $this->assertFalse($user->hasTwoFactorEnabled());
    }

    public function test_cli_command_can_disable_2fa_for_user(): void
    {
        $user = User::factory()->create([
            'email' => 'locked@nimbus.local',
            'two_factor_secret' => 'SECRETKEY',
            'two_factor_recovery_codes' => ['CODE1'],
            'two_factor_confirmed_at' => now(),
        ]);

        $this->artisan('nimbus:2fa-disable', [
            '--email' => 'locked@nimbus.local',
        ])->assertExitCode(0);

        $user->refresh();
        $this->assertFalse($user->hasTwoFactorEnabled());
    }
}
