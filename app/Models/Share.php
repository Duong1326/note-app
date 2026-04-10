<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Share extends Model
{
    const PERMISSION_READ = 'read';
    const PERMISSION_EDIT = 'edit';

    protected $fillable = [
        'note_id',
        'owner_id',
        'shared_with_user_id',
        'permission',
    ];

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class, 'note_id');
    }

    /** The user who shared the note */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** The user who received access to the note */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_with_user_id');
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function isReadOnly(): bool
    {
        return $this->permission === self::PERMISSION_READ;
    }

    public function canEdit(): bool
    {
        return $this->permission === self::PERMISSION_EDIT;
    }
}
