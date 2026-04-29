<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            // Stores bcrypt hash of the note's lock password (null = not locked)
            if (!Schema::hasColumn('notes', 'password_hash')) {
                $table->string('password_hash')->nullable()->after('content');
            }

            // Convenience flag for query filtering (true when password_hash is set)
            if (!Schema::hasColumn('notes', 'is_locked')) {
                $table->boolean('is_locked')->default(false)->after('password_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropColumn(['password_hash', 'is_locked']);
        });
    }
};
