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
        'file_path',
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

    /** Returns the publicly accessible URL for this attachment */
    public function url(): string
    {
        return \Storage::disk('public')->url($this->file_path);
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
