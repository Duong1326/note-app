<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_id')
                  ->constrained('notes')
                  ->cascadeOnDelete();

            $table->string('cloudinary_public_id');   // Cloudinary public_id (e.g. notes/5/abc123)
            $table->string('secure_url', 500);        // HTTPS URL từ Cloudinary
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable(); // bytes
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
