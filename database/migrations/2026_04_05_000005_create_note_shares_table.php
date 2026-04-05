<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_shares', function (Blueprint $table) {
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

            // When this share was created / last updated
            $table->timestamp('shared_at')->useCurrent();

            // A note can only be shared once per recipient
            $table->unique(['note_id', 'shared_with_user_id']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_shares');
    }
};
