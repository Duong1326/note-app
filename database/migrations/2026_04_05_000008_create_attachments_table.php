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

            $table->string('file_path');          // Đường dẫn file (khớp với Model)
            $table->string('disk')->default('public'); // Disk lưu trữ
            $table->string('mime_type', 100)->nullable(); // Loại file
            $table->unsignedBigInteger('size')->nullable(); // Dung lượng (bytes)
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
