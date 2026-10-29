<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'item_id', 'ip', 'user_agent', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, ?int $userId = null, ?string $itemId = null, array $meta = []): void
    {
        $req = request();
        static::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'item_id' => $itemId,
            'ip' => $req?->ip(),
            'user_agent' => mb_substr((string) $req?->userAgent(), 0, 255),
            'meta' => $meta ?: null,
        ]);
    }
}
