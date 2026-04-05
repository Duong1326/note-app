<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Note extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'content',
        'color',             // hex color string, e.g. "#ffffff"
        'is_pinned',         // bool – pinned to top
        'pinned_at',         // timestamp when pinned (for ordering multiple pins)
        'is_locked',         // bool – password-protected
        'lock_password',     // bcrypt hash of the note-level password
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_locked' => 'boolean',
        'pinned_at' => 'datetime',
    ];

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    /** The user who owns / created this note */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Labels attached to this note */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'note_label')
                    ->withTimestamps();
    }

    /** Image attachments */
    public function images(): HasMany
    {
        return $this->hasMany(NoteImage::class, 'note_id');
    }

    /** Share records for this note */
    public function shares(): HasMany
    {
        return $this->hasMany(NoteShare::class, 'note_id');
    }

    // ──────────────────────────────────────────────
    // Query Scopes
    // ──────────────────────────────────────────────

    /** Only notes owned by $userId */
    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Default sort: pinned first (by pinned_at desc), then by updated_at desc.
     */
    public function scopeDefaultOrder(Builder $query): Builder
    {
        return $query->orderByDesc('is_pinned')
                     ->orderByDesc('pinned_at')
                     ->orderByDesc('updated_at');
    }

    /** Full-text keyword search on title and content */
    public function scopeSearch(Builder $query, string $keyword): Builder
    {
        return $query->where(function (Builder $q) use ($keyword) {
            $q->where('title', 'like', "%{$keyword}%")
              ->orWhere('content', 'like', "%{$keyword}%");
        });
    }

    /** Filter notes that have at least one of the given label IDs */
    public function scopeWithLabels(Builder $query, array $labelIds): Builder
    {
        return $query->whereHas('labels', function (Builder $q) use ($labelIds) {
            $q->whereIn('labels.id', $labelIds);
        });
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function isPinned(): bool
    {
        return (bool) $this->is_pinned;
    }

    public function isLocked(): bool
    {
        return (bool) $this->is_locked;
    }

    public function isShared(): bool
    {
        return $this->shares()->exists();
    }

    /**
     * Verify a plain-text password against the note's lock_password hash.
     */
    public function verifyLockPassword(string $plain): bool
    {
        return password_verify($plain, $this->lock_password);
    }
}
