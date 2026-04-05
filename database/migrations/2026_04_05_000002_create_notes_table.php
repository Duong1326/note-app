<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();

            // Owner
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // Content
            $table->string('title');
            $table->longText('content')->nullable();

            // Appearance
            $table->string('color', 10)->nullable(); // hex, e.g. "#fef08a"

            // Pinning
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('pinned_at')->nullable(); // when it was pinned

            // Password lock
            $table->boolean('is_locked')->default(false);
            $table->string('lock_password')->nullable(); // bcrypt hash

            $table->timestamps(); // created_at, updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
