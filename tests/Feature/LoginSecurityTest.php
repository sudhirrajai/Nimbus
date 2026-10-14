<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LoginSecurityService;
use App\Support\LicenseGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginSecurityTest extends TestCase
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
            'machine_id'  => app(\App\Services\LicenseService::class)->getMachineId(),
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

    public function test_login_page_renders_without_captcha_initially(): void
    {
        $user = User::factory()->create();

        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Auth/Login')->where('captcha', null));
    }

    public function test_two_failed_logins_trigger_adaptive_captcha(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@test.local',
            'password' => Hash::make('correctpassword'),
        ]);

        // Attempt 1: Wrong password
        $this->post('/login', ['email' => 'admin@test.local', 'password' => 'wrong1'])->assertSessionHasErrors('email');

        // Attempt 2: Wrong password
        $this->post('/login', ['email' => 'admin@test.local', 'password' => 'wrong2'])->assertSessionHasErrors('email');

        // Now captcha is required
        $service = app(LoginSecurityService::class);
        $request = request();
        $this->assertTrue($service->requiresCaptcha($request));

        // Attempt 3 without captcha fails
        $response = $this->post('/login', ['email' => 'admin@test.local', 'password' => 'wrong3']);
        $response->assertSessionHasErrors('captcha_answer');
    }

    public function test_unban_command_clears_rate_limits(): void
    {
        $service = app(LoginSecurityService::class);
        $ip = '198.51.100.25';

        // Simulate a ban on that IP
        Cache::put('login_banned_ip_' . $ip, time() + 900, 900);
        Cache::put('login_fail_count_' . $ip, 12, 3600);

        $this->assertNotNull($service->getIpBanSecondsRemaining($ip));

        $this->artisan('nimbus:unban', ['--ip' => $ip])->assertExitCode(0);

        $this->assertNull($service->getIpBanSecondsRemaining($ip));
    }
}
