<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Totp;
use App\Services\VaultCrypto;
use App\Services\VaultService;
use App\Support\CredentialRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/** Instalação inicial, aceite de convite e recuperação de acesso. */
class AccountSetupController extends Controller
{
    public function __construct(private VaultService $vault)
    {
    }

    /* ---------- Instalação: primeiro administrador ---------- */

    public function showSetup()
    {
        abort_if(User::count() > 0, 404);
        return view('auth.setup');
    }

    public function setup(Request $request)
    {
        abort_if(User::count() > 0, 404);
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190',
            'password' => CredentialRules::password(),
            'phrase' => CredentialRules::phrase(),
        ]);

        $user = new User();
        $user->name = $data['name'];
        $user->email = Str::lower($data['email']);
        $user->role = 'admin';
        $user->is_active = true;
        $user->locale = app()->getLocale();
        $user->activated_at = now();
        $recovery = $this->vault->setupUserKeys($user, $data['password'], $data['phrase']);
        AuditLog::record('setup.admin_created', $user->id);

        return redirect()->route('recovery-key.show')->with('recovery_key', $recovery);
    }

    /* ---------- Convite ---------- */

    private function invitedUser(string $token): User
    {
        $user = User::where('invite_token_hash', hash('sha256', $token))->first();
        abort_if(! $user || ! $user->invite_expires_at || $user->invite_expires_at->isPast() || ! $user->is_active, 404);
        return $user;
    }

    public function showInvite(string $token)
    {
        $user = $this->invitedUser($token);
        return view('auth.invite', ['user' => $user, 'token' => $token]);
    }

    public function acceptInvite(Request $request, string $token)
    {
        $user = $this->invitedUser($token);
        $data = $request->validate([
            'password' => CredentialRules::password(),
            'phrase' => CredentialRules::phrase(),
        ]);

        $user->invite_token_hash = null;
        $user->invite_expires_at = null;
        $user->activated_at = now();
        $recovery = $this->vault->setupUserKeys($user, $data['password'], $data['phrase']);
        AuditLog::record('invite.accepted', $user->id);

        return redirect()->route('recovery-key.show')->with('recovery_key', $recovery);
    }

    public function showRecoveryKey(Request $request)
    {
        $key = $request->session()->get('recovery_key');
        if (! $key) {
            return redirect()->route('login');
        }
        $request->session()->keep('recovery_key');
        return view('auth.recovery-key', ['recoveryKey' => $key]);
    }

    /* ---------- Recuperação com a chave de recuperação ---------- */

    public function showRecover()
    {
        return view('auth.recover');
    }

    public function recover(Request $request)
    {
        $throttle = 'recover:' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttle, 5)) {
            return back()->withErrors(['email' => __('auth.throttle_simple')]);
        }
        RateLimiter::hit($throttle, 300);

        $data = $request->validate([
            'email' => 'required|email|max:190',
            'recovery_key' => 'required|string|max:80',
            'code' => 'required|string|max:10',
            'password' => CredentialRules::password(),
            'phrase' => CredentialRules::phrase(),
        ]);

        $user = User::where('email', Str::lower($data['email']))->first();
        $fail = fn () => back()->withErrors(['recovery_key' => __('auth.recover_failed')])->onlyInput('email');

        if (! $user || ! $user->is_active || ! $user->isActivated() || ! $user->hasTwoFactor() || $user->isLocked()) {
            return $fail();
        }

        $kp = $this->vault->unlockWithRecovery($user, $data['recovery_key']);
        $step = Totp::verify($user->two_factor_secret, $data['code'], $user->totp_last_step);
        if (! $kp || $step === null) {
            $user->failed_attempts++;
            if ($user->failed_attempts >= config('vault.max_attempts')) {
                $user->locked_until = now()->addMinutes(config('vault.lock_minutes'));
                $user->failed_attempts = 0;
            }
            $user->save();
            AuditLog::record('recover.fail', $user->id);
            return $fail();
        }

        $this->vault->wrapForCredentials($user, $kp, $data['password'], $data['phrase']);
        $user->totp_last_step = $step;
        $user->failed_attempts = 0;
        $user->save();
        AuditLog::record('recover.success', $user->id);
        RateLimiter::clear($throttle);

        return redirect()->route('login')->with('status', __('auth.recover_done'));
    }
}
