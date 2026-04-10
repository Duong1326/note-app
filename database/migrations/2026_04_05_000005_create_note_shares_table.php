<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shares', function (Blueprint $table) {
            $table->id();

            // The shared note
            $table->foreignId('note_id')
                  ->constrained('notes')
                  ->cascadeOnDelete();

            // Who shared it
            $table->foreignId('owner_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // Who received it
            $table->foreignId('shared_with_user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // 'read' | 'edit'
            $table->enum('permission', ['read', 'edit'])->default('read');

            $table->timestamps(); // created_at, updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shares');
    }
};
