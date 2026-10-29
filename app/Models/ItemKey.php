<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemKey extends Model
{
    protected $fillable = ['item_id', 'recipient_type', 'recipient_id', 'permission', 'wrapped_key', 'granted_by', 'reviewed_at'];

    protected $hidden = ['wrapped_key'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function recipientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function recipientGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'recipient_id');
    }

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function recipientName(): string
    {
        return $this->recipient_type === 'group'
            ? ($this->recipientGroup?->name ?? '—')
            : ($this->recipientUser?->name ?? '—');
    }
}
