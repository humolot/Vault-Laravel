<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\Item;
use App\Models\ItemKey;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class VaultService
{
    public const RANK = ['view' => 1, 'edit' => 2, 'owner' => 3];

    /** @var array<int,string|null> cache de pares de chaves de grupo */
    private array $groupKeys = [];

    /** @var array<int,array<int>> cache de grupos destravados por usuário */
    private array $unlockedGroups = [];

    public function __construct(private KeyVault $kv)
    {
    }

    /* =========================================================
     |  Chaves do usuário
     * ========================================================= */

    /** Cria/recria as chaves do usuário. Retorna a chave de recuperação (exibir uma única vez). */
    public function setupUserKeys(User $user, string $password, string $phrase): string
    {
        [$pub, $kp] = VaultCrypto::generateKeypair();
        $recovery = VaultCrypto::generateRecoveryKey();

        $user->public_key = $pub;
        $this->wrapForCredentials($user, $kp, $password, $phrase);
        $user->recovery_private_key_enc = VaultCrypto::encrypt(
            $kp,
            VaultCrypto::keyFromHighEntropy(VaultCrypto::normalizeRecoveryKey($recovery))
        );
        $user->save();

        return $recovery;
    }

    /** (Re)cifra o par de chaves com novas credenciais. */
    public function wrapForCredentials(User $user, string $kp, string $password, string $phrase): void
    {
        $phrase = VaultCrypto::normalizePhrase($phrase);
        $saltP = VaultCrypto::randomSalt();
        $saltF = VaultCrypto::randomSalt();
        $kek = VaultCrypto::combineKeys(
            VaultCrypto::deriveKey($password, $saltP),
            VaultCrypto::deriveKey($phrase, $saltF)
        );

        $user->kdf_salt_password = base64_encode($saltP);
        $user->kdf_salt_phrase = base64_encode($saltF);
        $user->private_key_enc = VaultCrypto::encrypt($kp, $kek);
        $user->password = Hash::make($password);
        $user->phrase_hash = Hash::make($phrase);
    }

    public function passwordKey(User $user, string $password): string
    {
        return VaultCrypto::deriveKey($password, base64_decode($user->kdf_salt_password));
    }

    public function unlockWithPhrase(User $user, string $passwordKey, string $phrase): ?string
    {
        try {
            $kek = VaultCrypto::combineKeys(
                $passwordKey,
                VaultCrypto::deriveKey(VaultCrypto::normalizePhrase($phrase), base64_decode($user->kdf_salt_phrase))
            );
            return VaultCrypto::decrypt($user->private_key_enc, $kek);
        } catch (Throwable) {
            return null;
        }
    }

    public function unlockWithRecovery(User $user, string $recoveryKey): ?string
    {
        if (! $user->recovery_private_key_enc) {
            return null;
        }
        try {
            return VaultCrypto::decrypt(
                $user->recovery_private_key_enc,
                VaultCrypto::keyFromHighEntropy(VaultCrypto::normalizeRecoveryKey($recoveryKey))
            );
        } catch (Throwable) {
            return null;
        }
    }

    public function regenerateRecoveryKey(User $user, string $kp): string
    {
        $recovery = VaultCrypto::generateRecoveryKey();
        $user->recovery_private_key_enc = VaultCrypto::encrypt(
            $kp,
            VaultCrypto::keyFromHighEntropy(VaultCrypto::normalizeRecoveryKey($recovery))
        );
        $user->save();
        return $recovery;
    }

    /** Administrador: destrói as chaves de um usuário (ele perde itens que só ele acessava). */
    public function resetUserKeys(User $user): void
    {
        DB::transaction(function () use ($user) {
            ItemKey::where('recipient_type', 'user')->where('recipient_id', $user->id)->update(['wrapped_key' => null]);
            DB::table('group_user')->where('user_id', $user->id)->update(['wrapped_key' => null]);
            $user->passkeys()->delete();
            foreach ([
                'public_key', 'private_key_enc', 'recovery_private_key_enc', 'kdf_salt_password', 'kdf_salt_phrase',
                'password', 'phrase_hash', 'two_factor_secret', 'two_factor_recovery_codes',
                'two_factor_confirmed_at', 'totp_last_step', 'activated_at',
            ] as $col) {
                $user->{$col} = null;
            }
            $user->passkey_required = false;
            $user->save();
        });
    }

    private function myKeypair(): string
    {
        $kp = $this->kv->keypair();
        if (! $kp) {
            throw new RuntimeException('Cofre bloqueado.');
        }
        return $kp;
    }

    /* =========================================================
     |  Grupos
     * ========================================================= */

    public function createGroup(User $creator, array $data): Group
    {
        [$pub, $gkp] = VaultCrypto::generateKeypair();
        return DB::transaction(function () use ($creator, $data, $pub, $gkp) {
            $group = new Group($data);
            $group->public_key = $pub;
            $group->created_by = $creator->id;
            $group->save();
            $group->users()->attach($creator->id, [
                'role' => 'manager',
                'wrapped_key' => VaultCrypto::seal($gkp, $creator->public_key),
                'added_by' => $creator->id,
            ]);
            return $group;
        });
    }

    public function groupKeypair(int $groupId, User $user): ?string
    {
        if (array_key_exists($groupId, $this->groupKeys)) {
            return $this->groupKeys[$groupId];
        }
        $row = DB::table('group_user')->where('group_id', $groupId)->where('user_id', $user->id)->first();
        $kp = null;
        if ($row && $row->wrapped_key) {
            try {
                $kp = VaultCrypto::sealOpen($row->wrapped_key, $this->myKeypair());
            } catch (Throwable) {
                $kp = null;
            }
        }
        return $this->groupKeys[$groupId] = $kp;
    }

    /** IDs dos grupos dos quais o usuário tem a chave. */
    public function unlockedGroupIds(User $user): array
    {
        return $this->unlockedGroups[$user->id] ??= DB::table('group_user')
            ->where('user_id', $user->id)->whereNotNull('wrapped_key')->pluck('group_id')->map(fn ($id) => (int) $id)->all();
    }

    public function addMember(Group $group, User $actor, User $target, string $role = 'member'): void
    {
        $gkp = $this->groupKeypair($group->id, $actor);
        $wrapped = ($gkp && $target->public_key) ? VaultCrypto::seal($gkp, $target->public_key) : null;

        $exists = DB::table('group_user')->where('group_id', $group->id)->where('user_id', $target->id)->exists();
        if ($exists) {
            DB::table('group_user')->where('group_id', $group->id)->where('user_id', $target->id)
                ->update(['role' => $role, 'updated_at' => now()] + ($wrapped ? ['wrapped_key' => $wrapped] : []));
            return;
        }
        $group->users()->attach($target->id, ['role' => $role, 'wrapped_key' => $wrapped, 'added_by' => $actor->id]);
    }

    public function removeMember(Group $group, User $target): void
    {
        DB::transaction(function () use ($group, $target) {
            $group->users()->detach($target->id);
            // O ex-membro pode ter visto as senhas: sugerir rotação dos itens do grupo.
            $itemIds = ItemKey::where('recipient_type', 'group')->where('recipient_id', $group->id)->pluck('item_id');
            Item::whereIn('id', $itemIds)->update(['rotation_flagged_at' => now()]);
        });
    }

    /* =========================================================
     |  Itens
     * ========================================================= */

    /** Resolve a chave do item e a permissão do usuário. */
    public function resolveAccess(Item $item, User $user): ?array
    {
        $kp = $this->myKeypair();
        $keys = $item->relationLoaded('keys') ? $item->keys : $item->keys()->get();
        $myGroups = array_flip($this->unlockedGroupIds($user));

        $candidates = $keys->filter(function (ItemKey $k) use ($user, $myGroups) {
            return $k->wrapped_key && (
                ($k->recipient_type === 'user' && (int) $k->recipient_id === (int) $user->id)
                || ($k->recipient_type === 'group' && isset($myGroups[$k->recipient_id]))
            );
        })->sortByDesc(fn (ItemKey $k) => self::RANK[$k->permission] ?? 0);

        foreach ($candidates as $k) {
            try {
                if ($k->recipient_type === 'user') {
                    $key = VaultCrypto::sealOpen($k->wrapped_key, $kp);
                } else {
                    $gkp = $this->groupKeypair((int) $k->recipient_id, $user);
                    if (! $gkp) {
                        continue;
                    }
                    $key = VaultCrypto::sealOpen($k->wrapped_key, $gkp);
                }
                $perm = $k->permission;
                if ((int) $item->owner_id === (int) $user->id) {
                    $perm = 'owner';
                }
                return ['key' => $key, 'permission' => $perm];
            } catch (Throwable) {
                continue;
            }
        }
        return null;
    }

    public function decryptPayload(Item $item, string $key): array
    {
        return VaultCrypto::decryptJson($item->payload, $key);
    }

    /** Consulta de itens em que o usuário tem alguma linha de acesso. */
    public function accessibleQuery(User $user)
    {
        $groupIds = DB::table('group_user')->where('user_id', $user->id)->pluck('group_id')->all();

        return Item::query()->where(function ($q) use ($user, $groupIds) {
            $q->where('owner_id', $user->id)
                ->orWhereIn('id', function ($sub) use ($user, $groupIds) {
                    $sub->select('item_id')->from('item_keys')
                        ->where(function ($w) use ($user, $groupIds) {
                            $w->where(fn ($a) => $a->where('recipient_type', 'user')->where('recipient_id', $user->id));
                            if ($groupIds) {
                                $w->orWhere(fn ($b) => $b->where('recipient_type', 'group')->whereIn('recipient_id', $groupIds));
                            }
                        });
                });
        });
    }

    /**
     * Lista itens decifrados para o usuário.
     * @return Collection<int, array{item: Item, data: array, permission: string, locked: bool}>
     */
    public function listFor(User $user, bool $trashed = false): Collection
    {
        $query = $this->accessibleQuery($user)->with(['keys', 'owner:id,name'])->withCount('attachments');
        if ($trashed) {
            $query->onlyTrashed()->where('owner_id', $user->id);
        }

        return $query->orderByDesc('updated_at')->get()->map(function (Item $item) use ($user) {
            $access = $this->resolveAccess($item, $user);
            if (! $access) {
                return (int) $item->owner_id === (int) $user->id
                    ? ['item' => $item, 'data' => [], 'permission' => 'owner', 'locked' => true]
                    : null;
            }
            try {
                $data = $this->decryptPayload($item, $access['key']);
            } catch (Throwable) {
                return null;
            }
            return ['item' => $item, 'data' => $data, 'permission' => $access['permission'], 'locked' => false];
        })->filter()->values();
    }

    public function createItem(User $user, string $type, array $data, ?int $rotationDays = null): Item
    {
        $key = VaultCrypto::randomKey();
        $data['history'] = [];

        return DB::transaction(function () use ($user, $type, $data, $rotationDays, $key) {
            $item = new Item();
            $item->type = $type;
            $item->owner_id = $user->id;
            $item->rotation_days = $rotationDays;
            $item->payload = VaultCrypto::encryptJson($data, $key);
            $item->password_changed_at = now();
            $item->updated_by = $user->id;
            $item->save();

            ItemKey::create([
                'item_id' => $item->id,
                'recipient_type' => 'user',
                'recipient_id' => $user->id,
                'permission' => 'owner',
                'wrapped_key' => VaultCrypto::seal($key, $user->public_key),
                'granted_by' => $user->id,
                'reviewed_at' => now(),
            ]);

            AuditLog::record('item.create', $user->id, $item->id, ['type' => $type]);
            return $item;
        });
    }

    public function updateItem(Item $item, User $user, string $key, array $new, ?int $rotationDays): void
    {
        $old = $this->decryptPayload($item, $key);
        $history = $old['history'] ?? [];
        $changed = false;

        foreach (config('vault.secret_fields') as $field) {
            $before = (string) ($old[$field] ?? '');
            $after = (string) ($new[$field] ?? '');
            if ($before !== '' && $before !== $after) {
                $changed = true;
                array_unshift($history, [
                    'field' => $field,
                    'value' => $before,
                    'changed_at' => now()->toIso8601String(),
                    'by' => $user->name,
                ]);
            } elseif ($before === '' && $after !== '') {
                $changed = true;
            }
        }

        $new['history'] = array_slice($history, 0, (int) config('vault.password_history', 5));
        $item->payload = VaultCrypto::encryptJson($new, $key);
        $item->rotation_days = $rotationDays;
        $item->updated_by = $user->id;
        if ($changed) {
            $item->password_changed_at = now();
            $item->rotation_flagged_at = null;
            $item->rotation_snoozed_until = null;
        }
        $item->save();
        AuditLog::record('item.update', $user->id, $item->id, ['secret_changed' => $changed]);
    }

    public function share(Item $item, User $actor, string $itemKey, string $type, int $recipientId, string $permission): ItemKey
    {
        if ($type === 'group') {
            $group = Group::findOrFail($recipientId);
            $wrapped = VaultCrypto::seal($itemKey, $group->public_key);
        } else {
            $target = User::findOrFail($recipientId);
            if (! $target->public_key) {
                throw new RuntimeException('Usuário ainda não ativou a conta.');
            }
            $wrapped = VaultCrypto::seal($itemKey, $target->public_key);
        }

        $row = ItemKey::updateOrCreate(
            ['item_id' => $item->id, 'recipient_type' => $type, 'recipient_id' => $recipientId],
            ['permission' => $permission, 'wrapped_key' => $wrapped, 'granted_by' => $actor->id, 'reviewed_at' => now()]
        );
        AuditLog::record('item.share', $actor->id, $item->id, ['to' => $type . ':' . $recipientId, 'permission' => $permission]);
        return $row;
    }

    public function revoke(Item $item, ItemKey $row, User $actor): void
    {
        if ($row->permission === 'owner') {
            throw new RuntimeException('Não é possível remover o dono.');
        }
        $row->delete();
        $item->rotation_flagged_at = now();
        $item->save();
        AuditLog::record('item.revoke', $actor->id, $item->id, ['from' => $row->recipient_type . ':' . $row->recipient_id]);
    }

    public function purgeItem(Item $item): void
    {
        foreach ($item->attachments as $att) {
            Storage::disk('local')->delete($att->path);
        }
        $item->forceDelete();
    }

    /* =========================================================
     |  Anexos
     * ========================================================= */

    public function storeAttachment(Item $item, User $user, string $itemKey, \Illuminate\Http\UploadedFile $file): Attachment
    {
        $att = new Attachment();
        $att->item_id = $item->id;
        $att->size = $file->getSize();
        $att->uploaded_by = $user->id;
        $att->meta = VaultCrypto::encryptJson([
            'name' => mb_substr($file->getClientOriginalName(), 0, 200),
            'mime' => $file->getMimeType() ?: 'application/octet-stream',
        ], $itemKey);
        $att->path = 'vault/' . strtolower((string) \Illuminate\Support\Str::ulid()) . '.bin';
        Storage::disk('local')->put($att->path, VaultCrypto::encryptRaw(file_get_contents($file->getRealPath()), $itemKey));
        $att->save();
        AuditLog::record('attachment.upload', $user->id, $item->id, ['size' => $att->size]);
        return $att;
    }

    public function attachmentMeta(Attachment $att, string $itemKey): array
    {
        try {
            return VaultCrypto::decryptJson($att->meta, $itemKey);
        } catch (Throwable) {
            return ['name' => 'arquivo', 'mime' => 'application/octet-stream'];
        }
    }

    public function attachmentContent(Attachment $att, string $itemKey): string
    {
        return VaultCrypto::decryptRaw(Storage::disk('local')->get($att->path), $itemKey);
    }

    /* =========================================================
     |  Reparo de chaves pendentes
     * ========================================================= */

    /**
     * Distribui chaves pendentes (novos membros de grupo ainda sem chave,
     * usuários reativados após reset) usando as chaves que o usuário atual possui.
     */
    public function heal(User $user): int
    {
        $fixed = 0;
        $myGroups = $this->unlockedGroupIds($user);

        if ($myGroups) {
            $pending = DB::table('group_user')
                ->join('users', 'users.id', '=', 'group_user.user_id')
                ->whereIn('group_user.group_id', $myGroups)
                ->whereNull('group_user.wrapped_key')
                ->whereNotNull('users.public_key')
                ->select('group_user.id', 'group_user.group_id', 'users.public_key')
                ->get();
            foreach ($pending as $p) {
                $gkp = $this->groupKeypair((int) $p->group_id, $user);
                if ($gkp) {
                    DB::table('group_user')->where('id', $p->id)->update(['wrapped_key' => VaultCrypto::seal($gkp, $p->public_key)]);
                    $fixed++;
                }
            }
        }

        $pendingKeys = ItemKey::query()
            ->where('recipient_type', 'user')
            ->whereNull('wrapped_key')
            ->whereIn('item_id', $this->accessibleQuery($user)->select('id'))
            ->get();
        foreach ($pendingKeys as $row) {
            $target = User::find($row->recipient_id);
            if (! $target?->public_key) {
                continue;
            }
            $item = Item::withTrashed()->with('keys')->find($row->item_id);
            $access = $item ? $this->resolveAccess($item, $user) : null;
            if ($access) {
                $row->wrapped_key = VaultCrypto::seal($access['key'], $target->public_key);
                $row->save();
                $fixed++;
            }
        }
        return $fixed;
    }
}
