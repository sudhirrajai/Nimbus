<?php

namespace App\Services;

use App\Models\SystemUser;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing Linux system users.
 * Phase 1: Stub implementation with basic structure.
 * Phase 2: Full implementation with user creation, PHP-FPM pools, etc.
 */
class SystemUserService
{
    /**
     * Create a Linux system user for tenant isolation.
     * Phase 2 will fully implement this.
     */
    public function createUser(int $userId, string $username): ?SystemUser
    {
        $homeDir = "/home/{$username}";

        try {
            // Phase 2: These commands will actually create the user
            // $this->executeCommand("useradd -m -s /bin/bash -d {$homeDir} {$username}");
            // $this->executeCommand("usermod -aG www-data {$username}");
            // $this->executeCommand("mkdir -p {$homeDir}/sites");
            // $this->executeCommand("chown -R {$username}:{$username} {$homeDir}");

            $systemUser = SystemUser::create([
                'user_id' => $userId,
                'username' => $username,
                'home_directory' => $homeDir,
                'is_active' => true,
            ]);

            Log::info("System user created: {$username} for panel user {$userId}");
            return $systemUser;

        } catch (\Exception $e) {
            Log::error("Failed to create system user {$username}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete a Linux system user.
     * Phase 2 will fully implement this.
     */
    public function deleteUser(string $username): bool
    {
        try {
            // Phase 2: $this->executeCommand("userdel -r {$username}");

            SystemUser::where('username', $username)->delete();
            Log::info("System user deleted: {$username}");
            return true;

        } catch (\Exception $e) {
            Log::error("Failed to delete system user {$username}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Suspend a system user (disable login).
     */
    public function suspendUser(string $username): bool
    {
        try {
            // Phase 2: $this->executeCommand("usermod -L {$username}");

            SystemUser::where('username', $username)->update(['is_active' => false]);
            return true;

        } catch (\Exception $e) {
            Log::error("Failed to suspend system user {$username}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate a system username from panel user ID.
     */
    public static function generateUsername(int $userId): string
    {
        return 'nimbus_' . $userId;
    }
}
