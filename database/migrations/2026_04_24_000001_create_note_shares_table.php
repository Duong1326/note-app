<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_shares', function (Blueprint $table): void {
            $table->id();

            // The note being shared
            $table->foreignId('note_id')
                  ->constrained('notes')
                  ->cascadeOnDelete();

            // The user who owns the note (denormalized for fast lookups)
            $table->foreignId('owner_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // The user the note is shared with
            $table->foreignId('shared_with_user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // Permission: 'read' or 'edit'
            $table->enum('permission', ['read', 'edit'])->default('read');

            $table->timestamps();

            // A user can only be shared a given note once
            $table->unique(['note_id', 'shared_with_user_id']);

            $table->index('shared_with_user_id');
            $table->index('note_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_shares');
    }
};
