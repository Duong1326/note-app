<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

    /** Notes shared WITH this user (as a recipient) */
    public function sharedNotes(): HasMany
    {
        return $this->hasMany(Share::class, 'shared_with_user_id');
    }

    /** User preferences (theme, font_size, note_color) */
    public function preference(): HasOne
    {
        return $this->hasOne(UserPreference::class, 'user_id');
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function isVerified(): bool
    {
        return $this->email_verified_at !== null;
    }
}
