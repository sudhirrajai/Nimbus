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
        Schema::create('recycle_bin_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('domain', 191)->nullable()->index();
            $table->string('original_name');
            $table->text('original_path');
            $table->string('trash_name', 191)->unique();
            $table->text('trash_path');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->boolean('is_directory')->default(false);
            $table->timestamp('created_at')->useCurrent()->index();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recycle_bin_items');
    }
};
