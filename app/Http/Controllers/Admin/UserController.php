<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\VaultService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(private VaultService $vault)
    {
    }

    public function index()
    {
        $users = User::withCount(['ownedItems', 'passkeys', 'groups'])->orderBy('name')->get();
        return view('admin.users', ['users' => $users]);
    }

    private function issueInvite(User $user): string
    {
        $token = Str::random(48);
        $user->invite_token_hash = hash('sha256', $token);
        $user->invite_expires_at = now()->addHours(config('vault.invite_hours'));
        $user->save();
        return route('invite.show', $token);
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190|unique:users,email',
            'role' => ['required', Rule::in(['user', 'admin'])],
            'locale' => ['required', Rule::in(['pt_BR', 'es', 'en'])],
        ]);
        $user = new User();
        $user->name = $v['name'];
        $user->email = Str::lower($v['email']);
        $user->role = $v['role'];
        $user->locale = $v['locale'];
        $user->is_active = true;
        $link = $this->issueInvite($user);
        AuditLog::record('admin.user_invited', $request->user()->id, null, ['user' => $user->id]);
        return back()->with('invite_link', $link)->with('invite_for', $user->email);
    }

    public function update(Request $request, User $user)
    {
        $me = $request->user();
        $action = $request->input('action');

        if ((int) $user->id === (int) $me->id && in_array($action, ['toggle', 'role', 'reset_account', 'reset_2fa'], true)) {
            return back()->withErrors(['user' => __('app.not_on_self')]);
        }

        switch ($action) {
            case 'toggle':
                $user->is_active = ! $user->is_active;
                $user->save();
                break;
            case 'role':
                $user->role = $user->role === 'admin' ? 'user' : 'admin';
                $user->save();
                break;
            case 'unlock':
                $user->locked_until = null;
                $user->failed_attempts = 0;
                $user->save();
                break;
            case 'reset_2fa':
                $user->two_factor_secret = null;
                $user->two_factor_confirmed_at = null;
                $user->two_factor_recovery_codes = null;
                $user->totp_last_step = null;
                $user->save();
                break;
            case 'invite':
                abort_if($user->isActivated(), 422);
                $link = $this->issueInvite($user);
                AuditLog::record('admin.' . $action, $me->id, null, ['user' => $user->id]);
                return back()->with('invite_link', $link)->with('invite_for', $user->email);
            case 'reset_account':
                $this->vault->resetUserKeys($user);
                $link = $this->issueInvite($user);
                AuditLog::record('admin.reset_account', $me->id, null, ['user' => $user->id]);
                return back()->with('invite_link', $link)->with('invite_for', $user->email);
            default:
                abort(422);
        }
        AuditLog::record('admin.' . $action, $me->id, null, ['user' => $user->id]);
        return back()->with('status', __('app.saved'));
    }

    public function destroy(Request $request, User $user)
    {
        if ((int) $user->id === (int) $request->user()->id) {
            return back()->withErrors(['user' => __('app.not_on_self')]);
        }
        if ($user->ownedItems()->withTrashed()->exists()) {
            return back()->withErrors(['user' => __('app.user_has_items')]);
        }
        AuditLog::record('admin.user_deleted', $request->user()->id, null, ['email' => $user->email]);
        $user->delete();
        return back()->with('status', __('app.deleted'));
    }

    public function audit(Request $request)
    {
        $q = AuditLog::with('user:id,name,email')->latest('created_at');
        if ($request->filled('user_id')) {
            $q->where('user_id', (int) $request->input('user_id'));
        }
        if ($request->filled('action')) {
            $q->where('action', 'like', str_replace(['%', '_'], ['\%', '\_'], $request->input('action')) . '%');
        }
        return view('admin.audit', [
            'logs' => $q->paginate(50)->withQueryString(),
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
