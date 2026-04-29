<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'note_id',
        'cloudinary_public_id',
        'secure_url',
        'mime_type',
        'size',
    ];

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class, 'note_id');
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    /** URL gốc của ảnh trên Cloudinary */
    public function url(): string
    {
        return $this->secure_url;
    }

    /** URL thumbnail với kích thước tuỳ chỉnh */
    public function thumbnailUrl(int $width = 400): string
    {
        return preg_replace(
            '#/upload/#',
            "/upload/c_fill,w_{$width},h_{$width},q_auto/",
            $this->secure_url,
            1
        );
    }

    /**
     * Boot: auto-set created_at on creation.
     */
    protected static function booted(): void
    {
        static::creating(function (Attachment $model) {
            $model->created_at = $model->created_at ?? now();
        });
    }
}
