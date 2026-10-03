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
        Schema::table('git_deployments', function (Blueprint $table) {
            if (!Schema::hasColumn('git_deployments', 'runtime_env')) {
                $table->json('runtime_env')->nullable()->after('yaml_config');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('git_deployments', function (Blueprint $table) {
            if (Schema::hasColumn('git_deployments', 'runtime_env')) {
                $table->dropColumn('runtime_env');
            }
        });
    }
};
