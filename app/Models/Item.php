<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = ['type', 'rotation_days'];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return [
            'password_changed_at' => 'datetime',
            'rotation_flagged_at' => 'datetime',
            'rotation_snoozed_until' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function keys(): HasMany
    {
        return $this->hasMany(ItemKey::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function isRotatable(): bool
    {
        return in_array($this->type, config('vault.rotatable_types', []), true);
    }

    /** Idade da senha em dias e se deve sugerir rotação. */
    public function rotationDue(User $viewer): bool
    {
        if (! $this->isRotatable()) {
            return false;
        }
        if ($this->rotation_snoozed_until && $this->rotation_snoozed_until->isFuture()) {
            return false;
        }
        if ($this->rotation_flagged_at) {
            return true;
        }
        $days = $this->rotation_days ?: $viewer->rotation_days ?: 90;
        $since = $this->password_changed_at ?? $this->created_at;
        return $since !== null && $since->lt(now()->subDays($days));
    }

    public function passwordAgeDays(): ?int
    {
        $since = $this->password_changed_at ?? $this->created_at;
        return $since ? (int) $since->diffInDays(now()) : null;
    }
}
