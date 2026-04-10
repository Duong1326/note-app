<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_passwords', function (Blueprint $table) {
            $table->id();

            $table->foreignId('note_id')
                  ->constrained('notes')
                  ->cascadeOnDelete();

            $table->string('password_hash'); // bcrypt

            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_passwords');
    }
};
