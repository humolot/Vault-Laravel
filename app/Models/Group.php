<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Group extends Model
{
    protected $fillable = ['name', 'description', 'color'];

    protected $hidden = ['public_key'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['role', 'wrapped_key', 'added_by'])->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function membership(User $user): ?object
    {
        return DB::table('group_user')->where('group_id', $this->id)->where('user_id', $user->id)->first();
    }

    public function isManager(User $user): bool
    {
        return $this->membership($user)?->role === 'manager';
    }
}
