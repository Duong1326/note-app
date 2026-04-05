<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('labels', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('name');

            $table->unique(['user_id', 'name']);

            $table->timestamps();
        });
        Schema::create('note_label', function (Blueprint $table) {
            $table->id();

            $table->foreignId('note_id')
                ->constrained('notes')
                ->cascadeOnDelete();

            $table->foreignId('label_id')
                ->constrained('labels')
                ->cascadeOnDelete();

            $table->unique(['note_id', 'label_id']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_label');
        Schema::dropIfExists('labels');
    }
};
