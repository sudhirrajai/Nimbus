<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResetAdminPasswordCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_fails_when_user_does_not_exist(): void
    {
        $this->artisan('nimbus:reset-admin-password', [
            '--email' => 'nonexistent@example.com',
            '--password' => 'secret1234',
        ])
        ->expectsOutput("Error: No user found with email 'nonexistent@example.com'.")
        ->assertExitCode(1);
    }

    public function test_fails_when_target_user_is_not_super_admin(): void
    {
        $normalUser = User::factory()->create([
            'email' => 'user@example.com',
            'role' => 'user',
        ]);

        $this->artisan('nimbus:reset-admin-password', [
            '--email' => 'user@example.com',
            '--password' => 'secret1234',
        ])
        ->expectsOutput("Security Restriction: User 'user@example.com' exists but has role 'user'.")
        ->assertExitCode(1);
    }

    public function test_successfully_resets_super_admin_password(): void
    {
        $rootUser = User::factory()->create([
            'email' => 'admin@nimbus.local',
            'role' => 'root',
            'password' => Hash::make('oldpassword'),
        ]);

        $this->artisan('nimbus:reset-admin-password', [
            '--email' => 'admin@nimbus.local',
            '--password' => 'newSecretPass123',
        ])
        ->assertExitCode(0);

        $rootUser->refresh();
        $this->assertTrue(Hash::check('newSecretPass123', $rootUser->password));
    }

    public function test_fails_when_password_too_short(): void
    {
        User::factory()->create([
            'email' => 'admin@nimbus.local',
            'role' => 'root',
        ]);

        $this->artisan('nimbus:reset-admin-password', [
            '--email' => 'admin@nimbus.local',
            '--password' => 'short',
        ])
        ->expectsOutput('Password must be at least 8 characters long.')
        ->assertExitCode(1);
    }
}
