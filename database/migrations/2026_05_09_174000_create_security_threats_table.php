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
        Schema::create('security_threats', function (Blueprint $table) {
            $table->id();
            $table->string('file_path');
            $table->string('type'); // malware, suspicious, shell, etc.
            $table->string('status')->default('detected'); // detected, quarantined, deleted, ignored
            $table->text('details')->nullable();
            $table->timestamp('detected_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_threats');
    }
};
