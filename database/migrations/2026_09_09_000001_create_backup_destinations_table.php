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
        Schema::create('backup_destinations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('driver'); // 'local', 'google_drive', 'backblaze', 's3', 'wasabi', 'r2', 'custom_s3'
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('credentials')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status')->nullable(); // 'success', 'failed'
            $table->text('last_test_error')->nullable();
            $table->timestamps();
        });

        Schema::table('backup_records', function (Blueprint $table) {
            $table->foreignId('destination_id')->nullable()->after('schedule_id')->constrained('backup_destinations')->nullOnDelete();
            $table->string('remote_status')->default('none')->after('storage_driver'); // 'none', 'pending', 'synced', 'failed'
            $table->string('remote_path')->nullable()->after('remote_status');
            $table->text('remote_error')->nullable()->after('remote_path');
        });

        Schema::table('backup_schedules', function (Blueprint $table) {
            $table->foreignId('destination_id')->nullable()->after('retention_count')->constrained('backup_destinations')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backup_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('destination_id');
        });

        Schema::table('backup_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('destination_id');
            $table->dropColumn(['remote_status', 'remote_path', 'remote_error']);
        });

        Schema::dropIfExists('backup_destinations');
    }
};
