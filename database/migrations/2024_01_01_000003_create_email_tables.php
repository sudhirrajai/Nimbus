<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates tables for virtual email management (Postfix/Dovecot)
     */
    public function up(): void
    {
        // Virtual domains - domains enabled for email
        Schema::create('virtual_domains', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // clotheeo.com
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Virtual users - email accounts
        Schema::create('virtual_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained('virtual_domains')->onDelete('cascade');
            $table->string('email')->unique(); // sudhir@clotheeo.com
            $table->string('password'); // Encrypted password for Dovecot
            $table->string('maildir'); // /var/mail/vhosts/clotheeo.com/sudhir/
            $table->integer('quota')->default(1024); // MB
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Virtual aliases - email forwarders
        Schema::create('virtual_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained('virtual_domains')->onDelete('cascade');
            $table->string('source'); // info@clotheeo.com
            $table->string('destination'); // sudhir@gmail.com
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->unique(['source', 'destination']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('virtual_aliases');
        Schema::dropIfExists('virtual_users');
        Schema::dropIfExists('virtual_domains');
    }
};
