<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('file_manager_scope', ['domain', 'projects', 'root'])
                ->default('domain')
                ->after('role');
        });

        // Ensure Root user (ID 1) has full 'root' server filesystem access
        DB::table('users')->where('id', 1)->update(['file_manager_scope' => 'root']);
        // Ensure any other users with role 'root' also get 'root'
        DB::table('users')->where('role', 'root')->update(['file_manager_scope' => 'root']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('file_manager_scope');
        });
    }
};
