<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Services\LicenseService;
use App\Support\LicenseGuard;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private $rootUser;
    private $adminUser;
    private $normalUser;

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
        
        $payloadB64 = $this->base64urlEncode($payload);
        $signatureB64 = $this->base64urlEncode($signature);
        
        return $payloadB64 . '.' . $signatureB64;
    }

    private function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
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

        $this->rootUser = User::factory()->create([
            'role' => 'root',
            'email' => 'root@nimbus.panel',
            'status' => 'active'
        ]);

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@nimbus.panel',
            'status' => 'active'
        ]);

        $this->normalUser = User::factory()->create([
            'role' => 'user',
            'email' => 'user@nimbus.panel',
            'status' => 'active'
        ]);
    }

    /**
     * Test GET request logs activity as 'view'
     */
    public function test_get_request_logs_page_view()
    {
        // 1. Visit dashboard
        $response = $this->actingAs($this->rootUser)->get('/dashboard');
        $response->assertStatus(200);

        // 2. Assert activity log exists
        $this->assertDatabaseHas('activity_logs', [
            'email' => 'root@nimbus.panel',
            'action' => 'view',
            'service' => 'dashboard',
            'description' => 'Viewed page: Dashboard'
        ]);
    }

    /**
     * Test POST request logs mutation
     */
    public function test_post_request_logs_mutation()
    {
        // 1. Post to update profile
        $response = $this->actingAs($this->rootUser)->postJson('/profile/update', [
            'name' => 'Root Administrator',
            'email' => 'root@nimbus.panel'
        ]);
        $response->assertStatus(302);

        // 2. Assert activity log exists
        $this->assertDatabaseHas('activity_logs', [
            'email' => 'root@nimbus.panel',
            'action' => 'update',
            'service' => 'profile',
            'description' => 'Updated profile information'
        ]);
    }

    /**
     * Test successful login logs activity
     */
    public function test_login_and_logout_log_activity()
    {
        // 1. Setup user
        $password = 'secret-pass-123';
        $user = User::factory()->create([
            'email' => 'test-logins@nimbus.panel',
            'password' => bcrypt($password),
            'role' => 'admin',
            'status' => 'active'
        ]);

        // 2. Post login
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => $password
        ]);
        $response->assertRedirect('/dashboard');

        // 3. Assert successful login logged
        $this->assertDatabaseHas('activity_logs', [
            'email' => 'test-logins@nimbus.panel',
            'action' => 'login',
            'service' => 'auth',
            'description' => 'Successfully logged in'
        ]);

        // 4. Logout
        $response = $this->actingAs($user)->post('/logout');
        $response->assertRedirect('/login');

        // 5. Assert logout logged
        $this->assertDatabaseHas('activity_logs', [
            'email' => 'test-logins@nimbus.panel',
            'action' => 'logout',
            'service' => 'auth',
            'description' => 'Logged out'
        ]);
    }

    /**
     * Test failed login logs activity
     */
    public function test_failed_login_logs_activity()
    {
        // 1. Post failed credentials
        $response = $this->post('/login', [
            'email' => 'fake-user@nimbus.panel',
            'password' => 'wrongpassword'
        ]);
        $response->assertStatus(302); // Redirect back with errors

        // 2. Assert failed login logged
        $this->assertDatabaseHas('activity_logs', [
            'email' => 'fake-user@nimbus.panel',
            'action' => 'failed_login',
            'service' => 'auth',
            'description' => 'Failed login attempt'
        ]);
    }

    /**
     * Test access controls for Activity Index and API List
     */
    public function test_access_controls_to_activity_logs()
    {
        // 1. Root user can access activities
        $response = $this->actingAs($this->rootUser)->get('/activities');
        $response->assertStatus(200);

        $response = $this->actingAs($this->rootUser)->getJson('/activities/list');
        $response->assertStatus(200);

        // 2. Admin user can access activities
        $response = $this->actingAs($this->adminUser)->get('/activities');
        $response->assertStatus(200);

        $response = $this->actingAs($this->adminUser)->getJson('/activities/list');
        $response->assertStatus(200);

        // 3. Normal user cannot access activities
        $response = $this->actingAs($this->normalUser)->get('/activities');
        $response->assertStatus(403); // Middleware role:root,admin blocks this

        $response = $this->actingAs($this->normalUser)->getJson('/activities/list');
        $response->assertStatus(403);
    }
}
