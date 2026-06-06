<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\LicenseService;
use App\Support\LicenseGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LicenseTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        LicenseGuard::reset();
        
        // Clear license-related settings before each test
        DB::table('settings')->whereIn('key', [
            'license_key',
            'license_token',
            'last_successful_license_check',
            'license_is_locked',
            'license_lock_reason',
            'license_degraded_at',
        ])->delete();

        Cache::forget('license_status');
    }

    /**
     * Helper to sign a payload and return a base64url encoded token.
     */
    private function generateToken(array $payloadData): string
    {
        $payload = json_encode($payloadData);
        $privateKey = openssl_pkey_get_private(self::TEST_PRIVATE_KEY);
        
        openssl_sign($payload, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        
        $payloadB64 = $this->base64urlEncode($payload);
        $signatureB64 = $this->base64urlEncode($signature);
        
        return $payloadB64 . '.' . $signatureB64;
    }

    private function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Test verification of a valid signed token.
     */
    public function test_can_verify_valid_rsa_signed_token()
    {
        $service = app(LicenseService::class);
        
        $payload = [
            'license_key' => 'NIMB-1234-5678-ABCD',
            'machine_id'  => 'test_machine_123',
            'plan'        => 'pro',
            'max_domains' => 10,
            'valid_until' => date('Y-m-d H:i:s', time() + 3600),
        ];

        $token = $this->generateToken($payload);
        
        $decoded = $service->verifySignedToken($token);
        
        $this->assertNotNull($decoded);
        $this->assertEquals('pro', $decoded['plan']);
        $this->assertEquals(10, $decoded['max_domains']);
        $this->assertEquals('test_machine_123', $decoded['machine_id']);
    }

    /**
     * Test signature mismatch validation.
     */
    public function test_fails_to_verify_tampered_token()
    {
        $service = app(LicenseService::class);
        
        $payload = [
            'license_key' => 'NIMB-1234-5678-ABCD',
            'machine_id'  => 'test_machine_123',
            'plan'        => 'pro',
            'max_domains' => 10,
            'valid_until' => date('Y-m-d H:i:s', time() + 3600),
        ];

        $token = $this->generateToken($payload);
        
        // Tamper with signature segment (parts[1])
        $parts = explode('.', $token);
        $parts[1][5] = $parts[1][5] === 'A' ? 'B' : 'A';
        $tamperedToken = implode('.', $parts);
        
        $decoded = $service->verifySignedToken($tamperedToken);
        
        $this->assertNull($decoded);
    }

    /**
     * Test LicenseGuard reflects system locked state.
     */
    public function test_license_guard_ok_returns_false_when_locked()
    {
        // Initial state should be false because no token is present
        $this->assertFalse(LicenseGuard::ok());

        // Store a valid token
        $payload = [
            'license_key' => 'NIMB-1234-5678-ABCD',
            'machine_id'  => 'test_machine_123',
            'plan'        => 'pro',
            'max_domains' => 10,
            'valid_until' => date('Y-m-d H:i:s', time() + 3600),
        ];
        $token = $this->generateToken($payload);
        
        DB::table('settings')->insert([
            ['key' => 'license_token', 'value' => $token, 'created_at' => now(), 'updated_at' => now()]
        ]);

        // The first check flagged the system as locked, so clear it to test normal flow
        DB::table('settings')->where('key', 'license_is_locked')->delete();

        LicenseGuard::reset();
        $this->assertTrue(LicenseGuard::ok());

        // Now set persistent lock
        DB::table('settings')->updateOrInsert(
            ['key' => 'license_is_locked'],
            ['value' => '1', 'updated_at' => now()]
        );

        LicenseGuard::reset();
        $this->assertFalse(LicenseGuard::ok());
    }

    /**
     * Test degradation timeline thresholds.
     */
    public function test_license_guard_is_blocked_respects_degradation_timeline()
    {
        // Force license check to fail
        DB::table('settings')->updateOrInsert(
            ['key' => 'license_is_locked'],
            ['value' => '1', 'updated_at' => now()]
        );
        LicenseGuard::reset();

        // 1. Degraded for 2 hours (within 6h window: warn but don't block anything)
        DB::table('settings')->updateOrInsert(
            ['key' => 'license_degraded_at'],
            ['value' => date('c', time() - 2 * 3600), 'updated_at' => now()]
        );
        LicenseGuard::reset();

        $this->assertFalse(LicenseGuard::isBlocked('create'));
        $this->assertFalse(LicenseGuard::isBlocked('critical'));
        $this->assertTrue(LicenseGuard::shouldShowWarning());
        $this->assertEquals(
            'System health check warning — some features may be affected. Please verify your system configuration.',
            LicenseGuard::warningMessage()
        );

        // 2. Degraded for 8 hours (between 6h and 24h: block resource creation, warn)
        DB::table('settings')->updateOrInsert(
            ['key' => 'license_degraded_at'],
            ['value' => date('c', time() - 8 * 3600), 'updated_at' => now()]
        );
        LicenseGuard::reset();

        $this->assertTrue(LicenseGuard::isBlocked('create'));
        $this->assertFalse(LicenseGuard::isBlocked('critical'));
        $this->assertTrue(LicenseGuard::shouldShowWarning());
        $this->assertEquals(
            'System integrity issue detected — resource creation is temporarily restricted. Contact support for assistance.',
            LicenseGuard::warningMessage()
        );

        // 3. Degraded for 26 hours (over 24h: block everything, critical warning)
        DB::table('settings')->updateOrInsert(
            ['key' => 'license_degraded_at'],
            ['value' => date('c', time() - 26 * 3600), 'updated_at' => now()]
        );
        LicenseGuard::reset();

        $this->assertTrue(LicenseGuard::isBlocked('create'));
        $this->assertTrue(LicenseGuard::isBlocked('critical'));
        $this->assertTrue(LicenseGuard::shouldShowWarning());
        $this->assertEquals(
            'Critical system issue detected — most operations are restricted. Immediate attention required. Contact support.',
            LicenseGuard::warningMessage()
        );
    }

    /**
     * Test that sendHeartbeat triggers a forced license check when status_changed_at is newer.
     */
    public function test_heartbeat_triggers_forced_license_check_when_newer_status_changed_at()
    {
        $service = app(LicenseService::class);

        // Seed settings
        DB::table('settings')->insert([
            ['key' => 'license_key', 'value' => 'NIMB-1234-5678-ABCD', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'last_status_sync_at', 'value' => date('c', time() - 3600), 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Fake the HTTP requests
        \Illuminate\Support\Facades\Http::fake([
            '*/api/v1/heartbeat' => \Illuminate\Support\Facades\Http::response([
                'status' => true,
                'message' => 'OK',
                'license_status' => 'active',
                'status_changed_at' => date('c', time()), // newer than 1 hour ago
            ], 200),
            '*/api/v1/verify' => \Illuminate\Support\Facades\Http::response([
                'status' => true,
                'plan' => 'pro',
                'expires_at' => 'Never',
                'message' => 'License is valid.',
                'signed_token' => $this->generateToken([
                    'license_key' => 'NIMB-1234-5678-ABCD',
                    'machine_id'  => $service->getMachineId(),
                    'plan'        => 'pro',
                    'max_domains' => 10,
                    'valid_until' => date('Y-m-d H:i:s', time() + 3600),
                ]),
                'max_domains' => 10,
                'status_changed_at' => date('c', time()),
            ], 200),
        ]);

        $result = $service->sendHeartbeat();

        $this->assertTrue($result);

        // Verify that verify endpoint was hit (meaning force check was triggered)
        \Illuminate\Support\Facades\Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/v1/verify');
        });
    }

    /**
     * Test that sendHeartbeat does not trigger a forced check when status_changed_at is older/same.
     */
    public function test_heartbeat_does_not_trigger_check_when_older_status_changed_at()
    {
        $service = app(LicenseService::class);

        // Seed settings
        $nowStr = date('c', time());
        DB::table('settings')->insert([
            ['key' => 'license_key', 'value' => 'NIMB-1234-5678-ABCD', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'last_status_sync_at', 'value' => $nowStr, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Fake the HTTP requests
        \Illuminate\Support\Facades\Http::fake([
            '*/api/v1/heartbeat' => \Illuminate\Support\Facades\Http::response([
                'status' => true,
                'message' => 'OK',
                'license_status' => 'active',
                'status_changed_at' => $nowStr, // same as local
            ], 200),
        ]);

        $result = $service->sendHeartbeat();

        $this->assertTrue($result);

        // Verify that verify endpoint was NOT hit
        \Illuminate\Support\Facades\Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/api/v1/verify');
        });
    }

    /**
     * Test that sendHeartbeat triggers immediate degradation if status is suspended (403 or 200 non-active).
     */
    public function test_heartbeat_triggers_degradation_if_suspended()
    {
        $service = app(LicenseService::class);

        DB::table('settings')->insert([
            ['key' => 'license_key', 'value' => 'NIMB-1234-5678-ABCD', 'created_at' => now(), 'updated_at' => now()],
        ]);

        \Illuminate\Support\Facades\Http::fake([
            '*/api/v1/heartbeat' => \Illuminate\Support\Facades\Http::response([
                'status' => false,
                'message' => 'License is suspended.',
            ], 403),
        ]);

        $result = $service->sendHeartbeat();

        $this->assertFalse($result);
        $this->assertTrue($service->isLocked());
    }
}
