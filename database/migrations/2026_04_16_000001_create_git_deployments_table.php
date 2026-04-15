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
        Schema::create('git_deployments', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->string('repo_url');
            $table->enum('repo_type', ['public', 'private'])->default('public');
            $table->enum('url_type', ['https', 'ssh'])->default('https');
            $table->text('access_token')->nullable();
            $table->string('branch')->default('main');
            $table->string('yaml_path')->nullable();
            $table->json('yaml_config')->nullable();
            $table->enum('status', [
                'pending',
                'cloning',
                'installing',
                'building',
                'completed',
                'failed'
            ])->default('pending');
            $table->text('last_error')->nullable();
            $table->string('commit_hash')->nullable();
            $table->string('system_user')->nullable();
            $table->timestamp('last_deployed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('git_deployments');
    }
};
