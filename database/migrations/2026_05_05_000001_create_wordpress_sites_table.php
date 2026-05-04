<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wordpress_sites', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->string('path');
            $table->string('wp_version')->nullable();
            $table->string('php_version')->default('8.2');
            $table->string('db_name')->nullable();
            $table->string('db_user')->nullable();
            $table->string('db_password')->nullable();
            $table->string('admin_user')->nullable();
            $table->string('admin_email')->nullable();
            $table->string('site_title')->nullable();
            $table->enum('status', ['active', 'inactive', 'installing', 'error'])->default('active');
            $table->boolean('auto_update')->default(false);
            $table->boolean('ssl_enabled')->default(false);
            $table->timestamp('last_checked_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wordpress_sites');
    }
};
