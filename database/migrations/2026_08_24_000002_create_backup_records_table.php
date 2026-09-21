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
        Schema::create('backup_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->nullable()->constrained('backup_schedules')->nullOnDelete();
            $table->string('domain')->nullable();
            $table->string('database_name')->nullable();
            $table->string('type')->default('full'); // 'database', 'files', 'full'
            $table->string('file_name');
            $table->string('file_path');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('storage_driver')->default('local');
            $table->string('status')->default('pending'); // 'pending', 'in_progress', 'completed', 'failed'
            $table->text('error_message')->nullable();
            $table->string('checksum')->nullable();
            $table->json('metadata')->nullable(); // Table counts, manifest details, etc.
            $table->string('created_by')->nullable(); // User email or 'Schedule: <id>'
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['domain', 'type']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_records');
    }
};
