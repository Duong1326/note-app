<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Workspaces table ──
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            // Default workspace cannot be deleted
            $table->boolean('is_default')->default(false);

            // Password protection (same pattern as notes)
            $table->boolean('is_locked')->default(false);
            $table->string('password_hash')->nullable();

            $table->timestamps();

            // Each user cannot have duplicate workspace names
            $table->unique(['user_id', 'name']);
        });

        // ── Workspace shares table ──
        Schema::create('workspace_shares', function (Blueprint $table) {
            $table->id();

            $table->foreignId('workspace_id')
                  ->constrained('workspaces')
                  ->cascadeOnDelete();

            // Denormalized owner for fast lookups
            $table->foreignId('owner_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->foreignId('shared_with_user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->enum('permission', ['read', 'edit'])->default('read');

            $table->timestamps();

            // A user can only be shared a given workspace once
            $table->unique(['workspace_id', 'shared_with_user_id']);
            $table->index('shared_with_user_id');
        });

        // ── Add workspace_id FK to notes table ──
        Schema::table('notes', function (Blueprint $table) {
            $table->foreignId('workspace_id')
                  ->nullable()
                  ->after('user_id')
                  ->constrained('workspaces')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropForeign(['workspace_id']);
            $table->dropColumn('workspace_id');
        });

        Schema::dropIfExists('workspace_shares');
        Schema::dropIfExists('workspaces');
    }
};
