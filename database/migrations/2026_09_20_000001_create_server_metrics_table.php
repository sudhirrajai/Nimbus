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
        Schema::create('server_metrics', function (Blueprint $table) {
            $table->id();
            $table->float('cpu_percent', 5, 2)->default(0);
            $table->float('memory_used_mb', 8, 2)->default(0);
            $table->float('memory_total_mb', 8, 2)->default(0);
            $table->float('memory_percent', 5, 2)->default(0);
            $table->float('swap_used_mb', 8, 2)->default(0);
            $table->float('disk_percent', 5, 2)->default(0);
            $table->float('load_1min', 5, 2)->default(0);
            $table->float('load_5min', 5, 2)->default(0);
            $table->float('load_15min', 5, 2)->default(0);
            $table->json('top_processes')->nullable();
            $table->boolean('is_alert_level')->default(false);
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_metrics');
    }
};
