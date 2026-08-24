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
        Schema::create('backup_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('domain')->nullable(); // Specific domain, 'all_domains', or null
            $table->string('database_name')->nullable(); // Specific db or null
            $table->string('type')->default('full'); // 'database', 'files', 'full'
            $table->string('frequency')->default('daily'); // 'hourly', 'daily', 'weekly', 'monthly'
            $table->string('time')->default('02:00'); // HH:MM in 24-hr format
            $table->integer('day_of_week')->nullable(); // 0 (Sun) to 6 (Sat) for weekly
            $table->integer('day_of_month')->nullable(); // 1 to 31 for monthly
            $table->integer('retention_count')->default(7); // Number of copies to retain
            $table->string('storage_driver')->default('local'); // 'local', 's3', etc.
            $table->json('storage_config')->nullable();
            $table->boolean('email_notifications')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->string('last_status')->nullable(); // 'success', 'failed', null
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_schedules');
    }
};
