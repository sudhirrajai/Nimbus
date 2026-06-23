<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserWebsite;
use App\Models\NimbusDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Services\LicenseService;
use App\Support\LicenseGuard;

class ScopingTest extends TestCase
{
    use RefreshDatabase;

    private $rootUser;
    private $adminUser1;
    private $adminUser2;

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

        // Generate a cryptographically valid token signed with the test key
        $token = $this->generateToken([
            'license_key' => 'NIMB-1234-5678-ABCD',
            'machine_id'  => app(LicenseService::class)->getMachineId(),
            'plan'        => 'pro',
            'max_domains' => 10,
            'valid_until' => date('Y-m-d H:i:s', time() + 3600),
        ]);

        // Seed settings to satisfy LicenseGuard/VerifyLicense check
        DB::table('settings')->insert([
            ['key' => 'license_key', 'value' => 'NIMB-1234-5678-ABCD', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'license_token', 'value' => $token, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'last_successful_license_check', 'value' => date('c'), 'created_at' => now(), 'updated_at' => now()]
        ]);

        LicenseGuard::reset();

        // Create a root user
        $this->rootUser = User::factory()->create([
            'role' => 'root',
            'email' => 'root@nimbus.panel'
        ]);

        // Create admin user 1 (assigned to domain1.com)
        $this->adminUser1 = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin1@nimbus.panel'
        ]);

        // Create admin user 2 (assigned to domain2.com)
        $this->adminUser2 = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin2@nimbus.panel'
        ]);

        // Assign domain1.com to adminUser1
        UserWebsite::create([
            'user_id' => $this->adminUser1->id,
            'domain' => 'domain1.com',
            'permissions' => ['nginx', 'supervisor', 'ssl', 'cron', 'email']
        ]);

        // Assign domain2.com to adminUser2
        UserWebsite::create([
            'user_id' => $this->adminUser2->id,
            'domain' => 'domain2.com',
            'permissions' => ['nginx', 'supervisor', 'ssl', 'cron', 'email']
        ]);
    }

    /**
     * Test Database Scoping
     */
    public function test_database_scoping()
    {
        // 1. Root user can access database endpoints without restriction
        $response = $this->actingAs($this->rootUser)->getJson('/database/list');
        $this->assertNotEquals(403, $response->getStatusCode());

        // 2. Admin 1 deleting database they don't own (e.g. domain2_db)
        $response = $this->actingAs($this->adminUser1)->postJson('/database/delete', [
            'name' => 'domain2_db'
        ]);
        $response->assertStatus(403);

        // 3. Admin 1 assigning user to database they don't own
        $response = $this->actingAs($this->adminUser1)->postJson('/database/user/assign', [
            'database' => 'domain2_db',
            'username' => 'dbuser',
            'privileges' => ['SELECT']
        ]);
        $response->assertStatus(403);

        // 4. Admin 1 updating permissions on database they don't own
        $response = $this->actingAs($this->adminUser1)->postJson('/database/user/permissions', [
            'database' => 'domain2_db',
            'username' => 'dbuser',
            'privileges' => ['SELECT']
        ]);
        $response->assertStatus(403);
    }

    /**
     * Test Nginx Scoping
     */
    public function test_nginx_scoping()
    {
        // Admin 1 accessing domain2.com config
        $response = $this->actingAs($this->adminUser1)->postJson('/nginx/config/read', [
            'domain' => 'domain2.com'
        ]);
        $response->assertStatus(403);

        // Admin 1 saving domain2.com config
        $response = $this->actingAs($this->adminUser1)->postJson('/nginx/config/save', [
            'domain' => 'domain2.com',
            'content' => 'server {}'
        ]);
        $response->assertStatus(403);

        // Admin 1 toggling domain2.com
        $response = $this->actingAs($this->adminUser1)->postJson('/nginx/toggle', [
            'domain' => 'domain2.com',
            'enabled' => true
        ]);
        $response->assertStatus(403);
    }

    /**
     * Test Supervisor Scoping
     */
    public function test_supervisor_scoping()
    {
        // Admin 1 starting domain2.com supervisor process
        $response = $this->actingAs($this->adminUser1)->postJson('/supervisor/start', [
            'name' => 'domain2.com:worker_00'
        ]);
        $response->assertStatus(403);

        // Admin 1 stopping domain2.com supervisor process
        $response = $this->actingAs($this->adminUser1)->postJson('/supervisor/stop', [
            'name' => 'domain2.com:worker_00'
        ]);
        $response->assertStatus(403);

        // Admin 1 creating supervisor process for domain2.com
        $response = $this->actingAs($this->adminUser1)->postJson('/supervisor/create', [
            'name' => 'unauthorized_worker',
            'project' => 'domain2.com',
            'command' => 'php artisan queue:work'
        ]);
        $response->assertStatus(403);

        // Admin 1 attempting global supervisor reload
        $response = $this->actingAs($this->adminUser1)->postJson('/supervisor/reload');
        $response->assertStatus(403);

        // Admin 1 attempting global supervisor actions (startAll, stopAll, restartAll)
        $response = $this->actingAs($this->adminUser1)->postJson('/supervisor/start-all');
        $response->assertStatus(403);

        $response = $this->actingAs($this->adminUser1)->postJson('/supervisor/stop-all');
        $response->assertStatus(403);

        $response = $this->actingAs($this->adminUser1)->postJson('/supervisor/restart-all');
        $response->assertStatus(403);
    }

    /**
     * Test SSL Certificate Scoping
     */
    public function test_ssl_scoping()
    {
        // Admin 1 installing certificate for domain2.com
        $response = $this->actingAs($this->adminUser1)->postJson('/ssl/install', [
            'domain' => 'domain2.com'
        ]);
        $response->assertStatus(403);

        // Admin 1 renewing certificate for domain2.com
        $response = $this->actingAs($this->adminUser1)->postJson('/ssl/renew', [
            'domain' => 'domain2.com'
        ]);
        $response->assertStatus(403);

        // Admin 1 removing certificate for domain2.com
        $response = $this->actingAs($this->adminUser1)->postJson('/ssl/remove', [
            'domain' => 'domain2.com'
        ]);
        $response->assertStatus(403);

        // Admin 1 attempting global renewAll
        $response = $this->actingAs($this->adminUser1)->postJson('/ssl/renew-all');
        $response->assertStatus(403);
    }

    /**
     * Test Cron Scoping
     */
    public function test_cron_scoping()
    {
        // Admin 1 creating cron job targeting domain2.com path
        $response = $this->actingAs($this->adminUser1)->postJson('/cron/create', [
            'user' => 'www-data',
            'minute' => '*',
            'hour' => '*',
            'day' => '*',
            'month' => '*',
            'weekday' => '*',
            'command' => 'php /var/www/domain2.com/artisan schedule:run'
        ]);
        $response->assertStatus(403);

        // Admin 1 deleting cron job targeting domain2.com path
        $response = $this->actingAs($this->adminUser1)->postJson('/cron/delete', [
            'user' => 'www-data',
            'command' => 'php /var/www/domain2.com/artisan schedule:run'
        ]);
        $response->assertStatus(403);

        // Admin 1 running cron job immediately targeting domain2.com path
        $response = $this->actingAs($this->adminUser1)->postJson('/cron/run', [
            'user' => 'www-data',
            'command' => 'php /var/www/domain2.com/artisan schedule:run'
        ]);
        $response->assertStatus(403);
    }

    /**
     * Test Logs Scoping
     */
    public function test_logs_scoping()
    {
        // Admin 1 reading domain2.com log path
        $response = $this->actingAs($this->adminUser1)->getJson('/logs/read?path=' . urlencode('/var/www/domain2.com/storage/logs/laravel.log'));
        $response->assertStatus(403);

        // Admin 1 clearing domain2.com log path
        $response = $this->actingAs($this->adminUser1)->postJson('/logs/clear', [
            'path' => '/var/www/domain2.com/storage/logs/laravel.log'
        ]);
        $response->assertStatus(403);

        // Admin 1 downloading domain2.com log path
        $response = $this->actingAs($this->adminUser1)->getJson('/logs/download?path=' . urlencode('/var/www/domain2.com/storage/logs/laravel.log'));
        $response->assertStatus(403);
    }

    /**
     * Test Email Scoping
     */
    public function test_email_scoping()
    {
        // Enable email for domain2.com
        $domainId = DB::table('virtual_domains')->insertGetId([
            'name' => 'domain2.com',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Create account on domain2.com
        $userId = DB::table('virtual_users')->insertGetId([
            'domain_id' => $domainId,
            'email' => 'admin@domain2.com',
            'password' => 'somehash',
            'maildir' => 'domain2.com/admin/',
            'quota' => 1024,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Create alias on domain2.com
        $aliasId = DB::table('virtual_aliases')->insertGetId([
            'domain_id' => $domainId,
            'source' => 'info@domain2.com',
            'destination' => 'admin@domain2.com',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // 1. Admin 1 enabling email for domain2.com
        $response = $this->actingAs($this->adminUser1)->postJson('/email/domain/enable', [
            'domain' => 'domain2.com'
        ]);
        $response->assertStatus(403);

        // 2. Admin 1 disabling email for domain2.com
        $response = $this->actingAs($this->adminUser1)->postJson('/email/domain/disable', [
            'domain' => 'domain2.com'
        ]);
        $response->assertStatus(403);

        // 3. Admin 1 creating email account on domain2.com
        $response = $this->actingAs($this->adminUser1)->postJson('/email/account/create', [
            'username' => 'testuser',
            'domain' => 'domain2.com',
            'password' => 'password123',
            'quota' => 1024
        ]);
        $response->assertStatus(403);

        // 4. Admin 1 deleting email account on domain2.com
        $response = $this->actingAs($this->adminUser1)->postJson('/email/account/delete', [
            'email' => 'admin@domain2.com'
        ]);
        $response->assertStatus(403);

        // 5. Admin 1 updating password on domain2.com email account
        $response = $this->actingAs($this->adminUser1)->postJson('/email/account/password', [
            'email' => 'admin@domain2.com',
            'password' => 'newpassword123'
        ]);
        $response->assertStatus(403);

        // 6. Admin 1 updating quota on domain2.com email account
        $response = $this->actingAs($this->adminUser1)->postJson('/email/account/quota', [
            'email' => 'admin@domain2.com',
            'quota' => 2048
        ]);
        $response->assertStatus(403);

        // 7. Admin 1 creating email alias on domain2.com
        $response = $this->actingAs($this->adminUser1)->postJson('/email/alias/create', [
            'source' => 'support@domain2.com',
            'destination' => 'admin@domain2.com'
        ]);
        $response->assertStatus(403);

        // 8. Admin 1 deleting email alias on domain2.com
        $response = $this->actingAs($this->adminUser1)->postJson('/email/alias/delete', [
            'id' => $aliasId
        ]);
        $response->assertStatus(403);

        // 9. Admin 1 generating SSO webmail login for domain2.com email account
        $response = $this->actingAs($this->adminUser1)->postJson('/email/webmail-login', [
            'email' => 'admin@domain2.com'
        ]);
        $response->assertStatus(403);

        // 10. Admin 1 reading client settings for domain2.com
        $response = $this->actingAs($this->adminUser1)->getJson('/email/client-settings?domain=domain2.com');
        $response->assertStatus(403);
    }
}
