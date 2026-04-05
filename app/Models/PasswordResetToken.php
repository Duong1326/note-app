<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PasswordResetToken extends Model
{
    const TYPE_LINK = 'link';
    const TYPE_OTP = 'otp';

    protected $primaryKey = 'email';
    public $incrementing = false;
    protected $keyType = 'string';

    // Use the standard laravel password_reset_tokens table
    protected $table = 'password_reset_tokens';

    protected $fillable = [
        'email',
        'token',
        'created_at',
        'expires_at',
    ];

    public $timestamps = false; // table only has created_at (no updated_at)

    protected $casts = [
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'email', 'email');
    }


    /** Verify a raw token/OTP against the stored hash */
    public function verify(string $rawToken): bool
    {
        return password_verify($rawToken, $this->token);
    }

    /** Whether this reset record has expired */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** Generate a cryptographically random URL token (64 chars) */
    public static function generateToken(): string
    {
        return Str::random(64);
    }

    /** Generate a 6-digit numeric OTP */
    public static function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
