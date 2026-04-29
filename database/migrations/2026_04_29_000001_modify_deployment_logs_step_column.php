<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('deployment_logs', function (Blueprint $table) {
            $table->string('step')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deployment_logs', function (Blueprint $table) {
            $table->enum('step', [
                'clone',
                'yaml_parse',
                'runtime_check',
                'install',
                'build',
                'env_setup',
                'permissions',
                'nginx_update',
                'supervisor_setup'
            ])->change();
        });
    }
};
