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
        'workspace_id',
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

    /** The workspace this note belongs to */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
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

    /**
     * Full-text keyword search on title and content.
     *
     * Uses MySQL FULLTEXT MATCH...AGAINST when at least one word meets
     * the InnoDB ft_min_word_len (default 3). Falls back to a LIKE-based
     * search when all words are too short for FULLTEXT to index them.
     *
     * For multi-word queries the LIKE fallback applies AND logic — every
     * word must appear in either title or content.
     *
     * (requires the ft_notes_search index added in the 2026_04_29 migration).
     */
    public function scopeSearch(Builder $query, string $keyword): Builder
    {
        $clean = trim($keyword);

        if ($clean === '') {
            return $query;
        }

        // Split into individual words (handles multiple spaces)
        $words = array_values(array_filter(preg_split('/\s+/u', $clean)));

        // InnoDB FULLTEXT default minimum word length is 3 characters.
        // Only attempt FULLTEXT if at least one word meets this threshold.
        $ftMinWordLen  = 3;
        $longWords     = array_filter($words, fn (string $w) => mb_strlen($w) >= $ftMinWordLen);

        if (count($longWords) > 0) {
            // Build the BOOLEAN MODE query with only words long enough
            // to be indexed. Short words are silently ignored by MySQL
            // anyway, so including them would cause zero matches.
            $safeWords = [];
            foreach ($longWords as $w) {
                // Strip special FULLTEXT operators
                $safe = str_replace(['*', '+', '-', '~', '<', '>', '(', ')', '"', '@'], '', $w);
                if ($safe !== '') {
                    $safeWords[] = '+' . $safe . '*';
                }
            }

            if (count($safeWords) > 0) {
                $ftQuery = implode(' ', $safeWords);

                return $query->whereRaw(
                    'MATCH(title, content) AGAINST(? IN BOOLEAN MODE)',
                    [$ftQuery]
                );
            }
        }

        // Fallback: LIKE-based search.
        // Each word must appear in title OR content (AND across words).
        return $query->where(function (Builder $outer) use ($words) {
            foreach ($words as $word) {
                $outer->where(function (Builder $q) use ($word) {
                    $q->where('title', 'like', '%' . $word . '%')
                      ->orWhere('content', 'like', '%' . $word . '%');
                });
            }
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
            'labels'      => $this->labels->filter(fn ($l) => auth()->check() ? $l->user_id === auth()->id() : true)->map(fn ($l) => ['id' => $l->id, 'name' => $l->name])->values(),
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
