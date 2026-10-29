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

class GroupController extends Controller
{
    public function __construct(private VaultService $vault)
    {
    }

    private function canManage(Group $group, User $user): bool
    {
        return $group->isManager($user);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Group::withCount(['users'])->orderBy('name');
        if (! $user->isAdmin()) {
            $query->whereIn('id', DB::table('group_user')->where('user_id', $user->id)->pluck('group_id'));
        }
        $groups = $query->get();
        $memberships = DB::table('group_user')->where('user_id', $user->id)->get()->keyBy('group_id');
        $itemCounts = ItemKey::where('recipient_type', 'group')->select('recipient_id', DB::raw('count(*) as c'))
            ->groupBy('recipient_id')->pluck('c', 'recipient_id');

        return view('groups.index', compact('groups', 'memberships', 'itemCounts'));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $v = $request->validate([
            'name' => 'required|string|max:80',
            'description' => 'nullable|string|max:255',
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
        $v['color'] = $v['color'] ?? '#3b82f6';
        $group = $this->vault->createGroup($request->user(), $v);
        AuditLog::record('group.create', $request->user()->id, null, ['group' => $group->id]);
        return redirect()->route('groups.show', $group)->with('status', __('app.saved'));
    }

    public function show(Request $request, Group $group)
    {
        $user = $request->user();
        $membership = $group->membership($user);
        abort_unless($membership || $user->isAdmin(), 403);

        $members = $group->users()->orderBy('name')->get();
        $candidates = User::where('is_active', true)
            ->whereNotIn('id', $members->pluck('id'))
            ->orderBy('name')->get(['id', 'name', 'email', 'public_key']);

        return view('groups.show', [
            'group' => $group,
            'members' => $members,
            'membership' => $membership,
            'canManage' => $membership && $membership->role === 'manager',
            'candidates' => $candidates,
            'itemCount' => ItemKey::where('recipient_type', 'group')->where('recipient_id', $group->id)->count(),
        ]);
    }

    public function update(Request $request, Group $group)
    {
        abort_unless($this->canManage($group, $request->user()) || $request->user()->isAdmin(), 403);
        $v = $request->validate([
            'name' => 'required|string|max:80',
            'description' => 'nullable|string|max:255',
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
        $group->fill($v)->save();
        return back()->with('status', __('app.saved'));
    }

    public function addMember(Request $request, Group $group)
    {
        abort_unless($this->canManage($group, $request->user()), 403);
        $v = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'role' => ['required', Rule::in(['member', 'manager'])],
        ]);
        $target = User::findOrFail($v['user_id']);
        $this->vault->addMember($group, $request->user(), $target, $v['role']);
        AuditLog::record('group.member_add', $request->user()->id, null, ['group' => $group->id, 'user' => $target->id]);
        return back()->with('status', __('app.member_added'));
    }

    public function updateMember(Request $request, Group $group, User $member)
    {
        abort_unless($this->canManage($group, $request->user()), 403);
        $v = $request->validate(['role' => ['required', Rule::in(['member', 'manager'])]]);
        if ($v['role'] === 'member' && $this->isLastManager($group, $member)) {
            return back()->withErrors(['role' => __('app.last_manager')]);
        }
        DB::table('group_user')->where('group_id', $group->id)->where('user_id', $member->id)->update(['role' => $v['role']]);
        return back()->with('status', __('app.saved'));
    }

    public function removeMember(Request $request, Group $group, User $member)
    {
        abort_unless($this->canManage($group, $request->user()) || (int) $member->id === (int) $request->user()->id, 403);
        if ($this->isLastManager($group, $member)) {
            return back()->withErrors(['role' => __('app.last_manager')]);
        }
        $this->vault->removeMember($group, $member);
        AuditLog::record('group.member_remove', $request->user()->id, null, ['group' => $group->id, 'user' => $member->id]);
        return (int) $member->id === (int) $request->user()->id
            ? redirect()->route('groups.index')->with('status', __('app.left_group'))
            : back()->with('status', __('app.member_removed'));
    }

    private function isLastManager(Group $group, User $member): bool
    {
        $managers = DB::table('group_user')->where('group_id', $group->id)->where('role', 'manager')->pluck('user_id');
        return $managers->count() === 1 && (int) $managers->first() === (int) $member->id;
    }

    public function destroy(Request $request, Group $group)
    {
        abort_unless($request->user()->isAdmin(), 403);
        DB::transaction(function () use ($group) {
            $itemIds = ItemKey::where('recipient_type', 'group')->where('recipient_id', $group->id)->pluck('item_id');
            Item::whereIn('id', $itemIds)->update(['rotation_flagged_at' => now()]);
            ItemKey::where('recipient_type', 'group')->where('recipient_id', $group->id)->delete();
            $group->delete();
        });
        AuditLog::record('group.delete', $request->user()->id, null, ['group' => $group->id]);
        return redirect()->route('groups.index')->with('status', __('app.deleted'));
    }
}
