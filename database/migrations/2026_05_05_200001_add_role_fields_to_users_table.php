<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['root', 'admin', 'user'])->default('user')->after('password');
            $table->string('linux_user')->nullable()->after('role');
            $table->enum('status', ['active', 'suspended'])->default('active')->after('linux_user');
            $table->timestamp('last_login_at')->nullable()->after('status');
        });

        // Set the first user (ID 1) as root
        DB::table('users')->where('id', 1)->update(['role' => 'root']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'linux_user', 'status', 'last_login_at']);
        });
    }
};
