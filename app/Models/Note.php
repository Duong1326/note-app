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
        'is_pinned',
        'pinned_at',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
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
        return $this->belongsToMany(Label::class, 'note_label');
    }

    /** Image/file attachments */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'note_id');
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
}
