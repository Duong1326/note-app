<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteShare extends Model
{
    protected $fillable = [
        'note_id',
        'owner_id',
        'shared_with_user_id',
        'permission',
    ];

    protected $casts = [
        'permission' => 'string',
    ];

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class, 'note_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function sharedWith(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_with_user_id');
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function canEdit(): bool
    {
        return $this->permission === 'edit';
    }

    public function canRead(): bool
    {
        return in_array($this->permission, ['read', 'edit']);
    }
}
