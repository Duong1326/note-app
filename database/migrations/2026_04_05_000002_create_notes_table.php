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
            $table->text('content')->nullable();

            // Pinning
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('pinned_at')->nullable();

            // Password lock flag (password stored in note_passwords table)
            $table->boolean('is_locked')->default(false);

            $table->timestamps(); // created_at, updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
