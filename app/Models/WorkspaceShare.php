<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceShare extends Model
{
    protected $fillable = [
        'workspace_id',
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

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
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
