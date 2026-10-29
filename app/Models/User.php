<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = [
        'name', 'email', 'role', 'is_active', 'locale', 'theme', 'rotation_days', 'access_review_days',
    ];

    protected $hidden = [
        'password', 'phrase_hash', 'private_key_enc', 'recovery_private_key_enc',
        'two_factor_secret', 'two_factor_recovery_codes', 'invite_token_hash',
        'kdf_salt_password', 'kdf_salt_phrase', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'passkey_required' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'invite_expires_at' => 'datetime',
            'activated_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function getRememberTokenName()
    {
        return null; // "lembrar-me" desativado por segurança
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isActivated(): bool
    {
        return $this->activated_at !== null && $this->public_key !== null;
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && ! empty($this->two_factor_secret);
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)->withPivot(['role', 'wrapped_key'])->withTimestamps();
    }

    public function passkeys(): HasMany
    {
        return $this->hasMany(Passkey::class);
    }

    public function ownedItems(): HasMany
    {
        return $this->hasMany(Item::class, 'owner_id');
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $ini = mb_substr($parts[0] ?? '?', 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '');
        return mb_strtoupper($ini);
    }
}
