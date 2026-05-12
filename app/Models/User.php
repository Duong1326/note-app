<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'bio',
        'avatar_url',
        'avatar_public_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    /** Notes owned by this user */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class, 'user_id');
    }

    /** Labels created by this user */
    public function labels(): HasMany
    {
        return $this->hasMany(Label::class, 'user_id');
    }

    /** Notes shared WITH this user (as a recipient) — returns NoteShare records */
    public function sharedNotes(): HasMany
    {
        return $this->hasMany(NoteShare::class, 'shared_with_user_id');
    }

    /** Workspaces owned by this user */
    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'user_id');
    }

    /** Workspaces shared WITH this user (as a recipient) */
    public function sharedWorkspaces(): HasMany
    {
        return $this->hasMany(WorkspaceShare::class, 'shared_with_user_id');
    }

    /**
     * Ensure the user has a default workspace named "Chung".
     * Creates one automatically if missing.
     */
    public function ensureDefaultWorkspace(): Workspace
    {
        $default = $this->workspaces()->where('is_default', true)->first();

        if (!$default) {
            $default = $this->workspaces()->create([
                'name'       => 'Chung',
                'description'=> 'Workspace mặc định',
                'is_default' => true,
            ]);
        }

        return $default;
    }


    // Helpers
    // ──────────────────────────────────────────────

    public function isVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function avatarUrl(): ?string
    {
        if (empty($this->avatar_url)) {
            return null;
        }

        // Chèn tham số AI Face crop vào URL (On-the-fly Transformation) để giảm tải gốc
        $transformedUrl = preg_replace(
            '#/upload/#',
            '/upload/c_fill,w_400,h_400,g_face,q_auto,f_auto/',
            $this->avatar_url,
            1
        );

        // Cache busting: append timestamp so browser always loads the latest avatar
        $timestamp = $this->updated_at ? $this->updated_at->timestamp : time();
        return $transformedUrl . '?t=' . $timestamp;
    }
}
