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

            $table->timestamp('created_at')->useCurrent();
        });

        // Pivot table with composite primary key per SRS
        Schema::create('note_label', function (Blueprint $table) {
            $table->foreignId('note_id')
                ->constrained('notes')
                ->cascadeOnDelete();

            $table->foreignId('label_id')
                ->constrained('labels')
                ->cascadeOnDelete();

            $table->primary(['note_id', 'label_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_label');
        Schema::dropIfExists('labels');
    }
};
