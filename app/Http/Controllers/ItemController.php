<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Group;
use App\Models\Item;
use App\Models\ItemKey;
use App\Models\User;
use App\Services\VaultService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class ItemController extends Controller
{
    public function __construct(private VaultService $vault)
    {
    }

    /** Garante acesso mínimo e devolve [chave, permissão]. */
    private function access(Item $item, string $min = 'view'): array
    {
        $access = $this->vault->resolveAccess($item, auth()->user());
        abort_if(! $access, 403);
        abort_if((VaultService::RANK[$access['permission']] ?? 0) < VaultService::RANK[$min], 403);
        return [$access['key'], $access['permission']];
    }

    public static function types(): array
    {
        return config('vault.types');
    }

    /* ------------------------------------------------------------ */

    public function index(Request $request)
    {
        $user = $request->user();

        if (! $request->session()->has('heal_at') || $request->session()->get('heal_at') < time() - 300) {
            try {
                $this->vault->heal($user);
            } catch (Throwable) {
            }
            $request->session()->put('heal_at', time());
        }

        $entries = $this->vault->listFor($user);

        $rows = $entries->map(function ($e) use ($user) {
            /** @var Item $item */
            $item = $e['item'];
            $d = $e['data'];
            $sharedCount = $item->keys->where('permission', '!=', 'owner')->count();
            return [
                'id' => $item->id,
                'type' => $item->type,
                'icon' => config("vault.types.{$item->type}.icon", 'bi-box-seam'),
                'name' => $d['name'] ?? __('app.locked_item'),
                'tags' => $d['tags'] ?? [],
                'login' => match ($item->type) {
                    'phone' => $d['phone'] ?? '',
                    'server' => trim(($d['username'] ?? '') . (isset($d['host']) && $d['host'] !== '' ? '@' . $d['host'] : '')),
                    default => $d['username'] ?? '',
                },
                'url' => $d['url'] ?? '',
                'has_password' => ($d['password'] ?? '') !== '',
                'has_secret' => ($d['secret'] ?? '') !== '',
                'permission' => $e['permission'],
                'owner' => $item->owner?->name,
                'mine' => (int) $item->owner_id === (int) $user->id,
                'shared' => $sharedCount > 0,
                'attachments' => $item->attachments_count,
                'rotation_due' => ! $e['locked'] && $e['permission'] !== 'view' && $item->rotationDue($user),
                'flagged' => (bool) $item->rotation_flagged_at,
                'age' => $item->passwordAgeDays(),
                'updated' => $item->updated_at,
                'locked' => $e['locked'],
            ];
        });

        $tags = $rows->pluck('tags')->flatten()->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();

        // Revisão de acessos: compartilhamentos antigos dos meus itens
        $reviewDays = $user->access_review_days ?: 180;
        $myItemIds = $rows->where('mine', true)->pluck('id');
        $names = $rows->pluck('name', 'id');
        $reviews = ItemKey::with(['recipientUser:id,name', 'recipientGroup:id,name'])
            ->whereIn('item_id', $myItemIds)
            ->where('permission', '!=', 'owner')
            ->where(fn ($q) => $q->whereNull('reviewed_at')->orWhere('reviewed_at', '<', now()->subDays($reviewDays)))
            ->limit(20)->get()
            ->map(fn (ItemKey $k) => ['key' => $k, 'item_name' => $names[$k->item_id] ?? '']);

        return view('items.index', [
            'rows' => $rows,
            'tags' => $tags,
            'rotation' => $rows->where('rotation_due', true)->values(),
            'reviews' => $reviews,
            'types' => self::types(),
        ]);
    }

    /* ------------------------------------------------------------ */

    private function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(self::types()))],
            'name' => 'required|string|max:150',
            'tags' => 'nullable|string|max:300',
            'username' => 'nullable|string|max:300',
            'password' => 'nullable|string|max:1000',
            'url' => 'nullable|string|max:2000',
            'secret' => 'nullable|string|max:20000',
            'phone' => 'nullable|string|max:50',
            'carrier' => 'nullable|string|max:100',
            'used_for' => 'nullable|string|max:2000',
            'host' => 'nullable|string|max:255',
            'port' => 'nullable|string|max:10',
            'notes' => 'nullable|string|max:20000',
            'rotation_days' => 'nullable|integer|min:1|max:3650',
        ];
    }

    private function payloadFrom(array $v): array
    {
        $fields = config("vault.types.{$v['type']}.fields", []);
        $tags = collect(explode(',', (string) ($v['tags'] ?? '')))
            ->map(fn ($t) => mb_substr(trim($t), 0, 40))->filter()->unique()->take(10)->values()->all();

        $payload = ['name' => trim($v['name']), 'tags' => $tags];
        foreach ($fields as $f) {
            $payload[$f] = (string) ($v[$f] ?? '');
        }
        return $payload;
    }

    public function create(Request $request)
    {
        $type = $request->query('type', 'login');
        if (! array_key_exists($type, self::types())) {
            $type = 'login';
        }
        return view('items.form', ['item' => null, 'data' => ['tags' => []], 'type' => $type, 'types' => self::types()]);
    }

    public function store(Request $request)
    {
        $v = $request->validate($this->rules());
        $item = $this->vault->createItem($request->user(), $v['type'], $this->payloadFrom($v), $v['rotation_days'] ?? null);
        return redirect()->route('items.show', $item)->with('status', __('app.saved'));
    }

    public function show(Request $request, Item $item)
    {
        [$key, $perm] = $this->access($item);
        $data = $this->vault->decryptPayload($item, $key);
        AuditLog::record('item.view', $request->user()->id, $item->id);

        $attachments = $item->attachments()->with('uploader:id,name')->latest()->get()->map(fn ($a) => [
            'model' => $a,
            'meta' => $this->vault->attachmentMeta($a, $key),
        ]);

        $shares = $perm === 'owner'
            ? $item->keys()->with(['recipientUser:id,name,email', 'recipientGroup:id,name,color', 'grantor:id,name'])->get()
            : collect();

        return view('items.show', [
            'item' => $item,
            'data' => $data,
            'perm' => $perm,
            'type' => self::types()[$item->type] ?? self::types()['other'],
            'attachments' => $attachments,
            'shares' => $shares,
            'users' => $perm === 'owner' ? User::where('is_active', true)->whereNotNull('public_key')->where('id', '!=', $request->user()->id)->orderBy('name')->get(['id', 'name', 'email']) : collect(),
            'groups' => $perm === 'owner' ? Group::orderBy('name')->get(['id', 'name', 'color']) : collect(),
            'rotationDue' => $perm !== 'view' && $item->rotationDue($request->user()),
            'history' => array_map(fn ($h) => array_diff_key($h, ['value' => 1]), $data['history'] ?? []),
        ]);
    }

    public function edit(Item $item)
    {
        [$key] = $this->access($item, 'edit');
        $data = $this->vault->decryptPayload($item, $key);
        return view('items.form', ['item' => $item, 'data' => $data, 'type' => $item->type, 'types' => self::types()]);
    }

    public function update(Request $request, Item $item)
    {
        [$key, $perm] = $this->access($item, 'edit');
        $v = $request->validate($this->rules());
        if ($perm !== 'owner') {
            $v['type'] = $item->type; // só o dono muda o tipo
        }
        $item->type = $v['type'];
        $this->vault->updateItem($item, $request->user(), $key, $this->payloadFrom($v), $v['rotation_days'] ?? null);
        return redirect()->route('items.show', $item)->with('status', __('app.saved'));
    }

    public function destroy(Request $request, Item $item)
    {
        $this->access($item, 'owner');
        $item->delete();
        AuditLog::record('item.trash', $request->user()->id, $item->id);
        return redirect()->route('dashboard')->with('status', __('app.moved_to_trash'));
    }

    /** Destrói item inacessível (chaves perdidas) — somente dono. */
    public function destroyLocked(Request $request, string $id)
    {
        $item = Item::where('owner_id', $request->user()->id)->findOrFail($id);
        abort_if($this->vault->resolveAccess($item, $request->user()) !== null, 403);
        $this->vault->purgeItem($item);
        AuditLog::record('item.purge_locked', $request->user()->id, $id);
        return back()->with('status', __('app.deleted'));
    }

    /* ------------------------------------------------------------ */

    public function reveal(Request $request, Item $item)
    {
        [$key] = $this->access($item);
        $data = $this->vault->decryptPayload($item, $key);
        $field = $request->input('field', 'password');
        $action = $request->input('action') === 'copy' ? 'item.copy' : 'item.reveal';

        if ($field === 'history') {
            $idx = (int) $request->input('index', 0);
            $value = $data['history'][$idx]['value'] ?? null;
        } elseif (in_array($field, ['password', 'secret'], true)) {
            $value = $data[$field] ?? null;
        } else {
            abort(422);
        }
        AuditLog::record($action, $request->user()->id, $item->id, ['field' => $field]);

        return response()->json(['value' => (string) $value])->header('Cache-Control', 'no-store');
    }

    public function snooze(Request $request, Item $item)
    {
        $this->access($item, 'edit');
        $days = (int) $request->input('days', 30);
        $item->rotation_snoozed_until = now()->addDays(max(1, min($days, 365)));
        $item->rotation_flagged_at = null;
        $item->save();
        return back()->with('status', __('app.snoozed', ['days' => $days]));
    }

    /* ---------------- Compartilhamento ---------------- */

    public function share(Request $request, Item $item)
    {
        [$key] = $this->access($item, 'owner');
        $v = $request->validate([
            'target' => ['required', 'string', 'regex:/^(user|group):\d+$/'],
            'permission' => ['required', Rule::in(['view', 'edit'])],
        ]);
        [$type, $id] = explode(':', $v['target']);
        if ($type === 'user' && (int) $id === (int) $request->user()->id) {
            return back()->withErrors(['target' => __('app.cannot_share_self')]);
        }
        try {
            $this->vault->share($item, $request->user(), $key, $type, (int) $id, $v['permission']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['target' => __('app.share_user_inactive')]);
        }
        return back()->with('status', __('app.shared'));
    }

    public function updateShare(Request $request, Item $item, ItemKey $share)
    {
        $this->access($item, 'owner');
        abort_if($share->item_id !== $item->id || $share->permission === 'owner', 404);
        $v = $request->validate(['permission' => ['nullable', Rule::in(['view', 'edit'])]]);
        if (! empty($v['permission'])) {
            $share->permission = $v['permission'];
        }
        $share->reviewed_at = now();
        $share->save();
        AuditLog::record('item.share_review', $request->user()->id, $item->id, ['share' => $share->id]);
        return back()->with('status', __('app.saved'));
    }

    public function revoke(Request $request, Item $item, ItemKey $share)
    {
        $this->access($item, 'owner');
        abort_if($share->item_id !== $item->id, 404);
        $this->vault->revoke($item, $share, $request->user());
        return back()->with('status', __('app.revoked'));
    }

    /* ---------------- Lixeira ---------------- */

    public function trash(Request $request)
    {
        $entries = $this->vault->listFor($request->user(), true);
        return view('items.trash', ['entries' => $entries, 'types' => self::types()]);
    }

    public function restore(Request $request, string $id)
    {
        $item = Item::onlyTrashed()->where('owner_id', $request->user()->id)->findOrFail($id);
        $item->restore();
        AuditLog::record('item.restore', $request->user()->id, $item->id);
        return back()->with('status', __('app.restored'));
    }

    public function purge(Request $request, string $id)
    {
        $item = Item::onlyTrashed()->where('owner_id', $request->user()->id)->findOrFail($id);
        DB::transaction(fn () => $this->vault->purgeItem($item));
        AuditLog::record('item.purge', $request->user()->id, $id);
        return back()->with('status', __('app.deleted'));
    }
}
