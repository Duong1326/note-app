<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Workspace extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'is_default',
        'is_locked',
        'password_hash',
    ];

    /** Never expose the raw password hash in JSON / API responses. */
    protected $hidden = ['password_hash'];

    protected $casts = [
        'is_default' => 'boolean',
        'is_locked'  => 'boolean',
    ];

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    /** The user who owns / created this workspace */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Notes inside this workspace */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class, 'workspace_id');
    }

    /** Share records for this workspace */
    public function shares(): HasMany
    {
        return $this->hasMany(WorkspaceShare::class, 'workspace_id');
    }

    // ──────────────────────────────────────────────
    // Query Scopes
    // ──────────────────────────────────────────────

    /** Only workspaces owned by $userId */
    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function isPasswordProtected(): bool
    {
        return $this->is_locked && !empty($this->password_hash);
    }

    /**
     * JSON representation for workspace list items.
     */
    public function toListArray(): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'is_default'  => $this->is_default,
            'is_locked'   => $this->is_locked,
            'notes_count' => $this->notes_count ?? $this->notes()->count(),
            'shares_count'=> $this->shares_count ?? $this->shares()->count(),
            'updated_at'  => $this->updated_at?->diffForHumans(),
        ];
    }
}
