<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteImage extends Model
{
    protected $fillable = [
        'note_id',
        'path',        // relative path stored in storage (e.g. "note-images/abc.jpg")
        'disk',        // storage disk name: 'public' | 's3' | etc.
        'mime_type',   // e.g. "image/jpeg"
        'size',        // file size in bytes
        'original_name', // original filename uploaded by user
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

    /** Returns the publicly accessible URL for this image */
    public function url(): string
    {
        return \Storage::disk($this->disk ?? 'public')->url($this->path);
    }
}
