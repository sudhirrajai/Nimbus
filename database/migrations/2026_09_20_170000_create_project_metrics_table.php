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
        Schema::create('project_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->index();
            $table->string('system_user')->nullable()->index();
            $table->float('cpu_percent')->default(0.0);
            $table->float('memory_mb')->default(0.0);
            $table->float('memory_percent')->default(0.0);
            $table->float('disk_mb')->default(0.0);
            $table->integer('process_count')->default(0);
            $table->timestamp('created_at')->index();

            // Composite index for fast time-series queries per domain
            $table->index(['domain', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_metrics');
    }
};
