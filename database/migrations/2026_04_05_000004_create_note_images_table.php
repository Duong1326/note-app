<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_images', function (Blueprint $table) {
            $table->id();

            $table->foreignId('note_id')
                  ->constrained('notes')
                  ->cascadeOnDelete();

            // Where the file is stored
            $table->string('path');                       // relative path on disk
            $table->string('disk', 30)->default('public'); // storage disk name
            $table->string('mime_type', 50)->nullable();
            $table->unsignedBigInteger('size')->nullable(); // bytes
            $table->string('original_name')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_images');
    }
};
