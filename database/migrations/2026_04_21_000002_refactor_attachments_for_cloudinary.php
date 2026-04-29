<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor attachments table to store Cloudinary metadata only.
 * - Rename file_path → cloudinary_public_id
 * - Add secure_url column
 * (disk column already removed in cleanup migration)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            // Rename file_path to cloudinary_public_id (if not already renamed)
            if (Schema::hasColumn('attachments', 'file_path') && !Schema::hasColumn('attachments', 'cloudinary_public_id')) {
                $table->renameColumn('file_path', 'cloudinary_public_id');
            }

            // Add secure_url if not exists
            if (!Schema::hasColumn('attachments', 'secure_url')) {
                $table->string('secure_url', 500)->after('cloudinary_public_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            if (Schema::hasColumn('attachments', 'secure_url')) {
                $table->dropColumn('secure_url');
            }

            if (Schema::hasColumn('attachments', 'cloudinary_public_id') && !Schema::hasColumn('attachments', 'file_path')) {
                $table->renameColumn('cloudinary_public_id', 'file_path');
            }
        });
    }
};
