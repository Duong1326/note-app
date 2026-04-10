<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotePassword extends Model
{
    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'note_id',
        'password_hash',
    ];

    protected $hidden = [
        'password_hash',
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

    /** Verify a plain-text password against the stored hash */
    public function verify(string $plain): bool
    {
        return password_verify($plain, $this->password_hash);
    }

    /**
     * Boot: auto-set created_at on creation.
     */
    protected static function booted(): void
    {
        static::creating(function (NotePassword $model) {
            $model->created_at = $model->created_at ?? now();
        });
    }
}
