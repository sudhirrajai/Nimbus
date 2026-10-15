<?php

namespace Tests\Feature;

use App\Models\FtpAccount;
use App\Models\User;
use App\Models\UserWebsite;
use App\Services\FtpService;
use App\Support\LicenseGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FtpScopingTest extends TestCase
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

    private $rootUser;

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

        // Ensure root user is ID 1
        $this->rootUser = User::factory()->create([
            'role' => 'root',
            'email' => 'root@nimbus.panel'
        ]);
    }

    public function test_super_admin_sees_all_ftp_accounts(): void
    {
        $superAdmin = $this->rootUser;
        $user1 = User::factory()->create(['role' => 'user', 'email' => 'user1@nimbus.panel']);
        $user2 = User::factory()->create(['role' => 'user', 'email' => 'user2@nimbus.panel']);

        $acc1 = FtpAccount::create([
            'user_id' => $user1->id,
            'domain' => 'domain1.com',
            'username' => 'user1_ftp',
            'homedir' => '/var/www/domain1.com',
            'quota_mb' => 500,
            'is_active' => true,
        ]);

        $acc2 = FtpAccount::create([
            'user_id' => $user2->id,
            'domain' => 'domain2.com',
            'username' => 'user2_ftp',
            'homedir' => '/var/www/domain2.com',
            'quota_mb' => 1000,
            'is_active' => true,
        ]);

        $ftpService = app(FtpService::class);
        $accounts = $ftpService->listAccounts($superAdmin);

        $this->assertCount(2, $accounts);
        $this->assertTrue($accounts->contains('id', $acc1->id));
        $this->assertTrue($accounts->contains('id', $acc2->id));
    }

    public function test_regular_user_only_sees_their_own_created_ftp_accounts(): void
    {
        $user1 = User::factory()->create(['role' => 'user', 'email' => 'user1@nimbus.panel']);
        $user2 = User::factory()->create(['role' => 'user', 'email' => 'user2@nimbus.panel']);

        $acc1 = FtpAccount::create([
            'user_id' => $user1->id,
            'domain' => 'domain1.com',
            'username' => 'user1_ftp',
            'homedir' => '/var/www/domain1.com',
            'quota_mb' => 500,
            'is_active' => true,
        ]);

        $acc2 = FtpAccount::create([
            'user_id' => $user2->id,
            'domain' => 'domain2.com',
            'username' => 'user2_ftp',
            'homedir' => '/var/www/domain2.com',
            'quota_mb' => 1000,
            'is_active' => true,
        ]);

        $ftpService = app(FtpService::class);
        $accountsUser1 = $ftpService->listAccounts($user1);
        $accountsUser2 = $ftpService->listAccounts($user2);

        $this->assertCount(1, $accountsUser1);
        $this->assertEquals($acc1->id, $accountsUser1->first()->id);

        $this->assertCount(1, $accountsUser2);
        $this->assertEquals($acc2->id, $accountsUser2->first()->id);
    }

    public function test_user_cannot_update_or_delete_another_users_ftp_account(): void
    {
        $user1 = User::factory()->create(['role' => 'user', 'email' => 'user1@nimbus.panel']);
        $user2 = User::factory()->create(['role' => 'user', 'email' => 'user2@nimbus.panel']);

        UserWebsite::create([
            'user_id' => $user1->id,
            'domain' => 'domain1.com',
            'permissions' => ['files'],
        ]);

        UserWebsite::create([
            'user_id' => $user2->id,
            'domain' => 'domain2.com',
            'permissions' => ['files'],
        ]);

        $account = FtpAccount::create([
            'user_id' => $user1->id,
            'domain' => 'domain1.com',
            'username' => 'user1_ftp',
            'homedir' => '/var/www/domain1.com',
            'quota_mb' => 500,
            'is_active' => true,
        ]);

        // User 2 attempts to change User 1's password
        $res = $this->actingAs($user2)->put('/ftp/' . $account->id . '/password', [
            'password' => 'newpassword123',
        ]);
        $res->assertSessionHas('error', 'Unauthorized action.');

        // User 2 attempts to change User 1's quota
        $res = $this->actingAs($user2)->put('/ftp/' . $account->id . '/quota', [
            'quota_mb' => 2000,
        ]);
        $res->assertSessionHas('error', 'Unauthorized action.');

        // User 2 attempts to toggle User 1's status
        $res = $this->actingAs($user2)->post('/ftp/' . $account->id . '/toggle-status');
        $res->assertSessionHas('error', 'Unauthorized action.');

        // User 2 attempts to delete User 1's account
        $res = $this->actingAs($user2)->delete('/ftp/' . $account->id);
        $res->assertSessionHas('error', 'Unauthorized action.');

        // Account still exists
        $this->assertDatabaseHas('ftp_accounts', ['id' => $account->id]);
    }

    public function test_super_admin_can_manage_any_users_ftp_account(): void
    {
        $user1 = User::factory()->create(['role' => 'user', 'email' => 'user1@nimbus.panel']);

        $account = FtpAccount::create([
            'user_id' => $user1->id,
            'domain' => 'domain1.com',
            'username' => 'user1_ftp',
            'homedir' => '/var/www/domain1.com',
            'quota_mb' => 500,
            'is_active' => true,
        ]);

        // Super Admin toggles status
        $res = $this->actingAs($this->rootUser)->post('/ftp/' . $account->id . '/toggle-status');
        $res->assertSessionHas('success');
        $this->assertEquals(false, $account->fresh()->is_active);

        // Super Admin updates quota
        $res = $this->actingAs($this->rootUser)->put('/ftp/' . $account->id . '/quota', [
            'quota_mb' => 2500,
        ]);
        $res->assertSessionHas('success');
        $this->assertEquals(2500, $account->fresh()->quota_mb);

        // Super Admin deletes account
        $res = $this->actingAs($this->rootUser)->delete('/ftp/' . $account->id);
        $res->assertSessionHas('success');
        $this->assertDatabaseMissing('ftp_accounts', ['id' => $account->id]);
    }
}
