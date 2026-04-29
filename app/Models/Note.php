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
        'password_hash',
        'is_locked',
    ];

    /**
     * Never expose the raw password hash in JSON / API responses.
     */
    protected $hidden = ['password_hash'];

    protected $casts = [
        'is_pinned' => 'boolean',
        'pinned_at' => 'datetime',
        'is_locked' => 'boolean',
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

    /** Share records for this note */
    public function shares(): HasMany
    {
        return $this->hasMany(NoteShare::class, 'note_id');
    }

    /** Get the share record for a specific user */
    public function shareFor(int $userId): ?NoteShare
    {
        return $this->shares()->where('shared_with_user_id', $userId)->first();
    }

    // ──────────────────────────────────────────────
    // Query Scopes
    // ──────────────────────────────────────────────

    /** Only notes owned by $userId */
    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /** Notes shared with $userId (via note_shares) */
    public function scopeSharedWith(Builder $query, int $userId): Builder
    {
        return $query->whereHas('shares', function (Builder $q) use ($userId) {
            $q->where('shared_with_user_id', $userId);
        });
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

    public function isPasswordProtected(): bool
    {
        return $this->is_locked && !empty($this->password_hash);
    }

    /**
     * Standard JSON representation for note cards (used by API responses).
     * Centralises the mapping that was previously duplicated across controllers.
     */
    public function toCardArray(): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'content'     => $this->content,
            'is_pinned'   => $this->is_pinned,
            'is_locked'   => $this->is_locked,
            'updated_at'  => $this->updated_at?->diffForHumans(),
            'labels'      => $this->labels->map(fn ($l) => ['id' => $l->id, 'name' => $l->name])->values(),
            'attachments' => $this->attachments->map(fn ($a) => [
                'id'            => $a->id,
                'url'           => $a->secure_url,
                'thumbnail_url' => $a->thumbnailUrl(400),
            ])->values(),
        ];
    }

    /**
     * Extended card array that includes owner info (for shared note views).
     */
    public function toSharedCardArray(): array
    {
        return array_merge($this->toCardArray(), [
            'owner' => [
                'name'       => $this->user->name ?? '',
                'avatar_url' => $this->user?->avatarUrl(),
            ],
        ]);
    }
}
