<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('font_size', 10)->default('md')->after('remember_token');
            $table->string('theme', 10)->default('light')->after('font_size');
            $table->string('note_color', 10)->nullable()->after('theme');
        });

        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->string('type', 10)->default('link')->after('token');
            $table->timestamp('expires_at')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['font_size', 'theme', 'note_color']);
        });

        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->dropColumn(['type', 'expires_at']);
        });
    }
};
