<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\KeyVault;
use App\Services\Passkeys;
use App\Services\Totp;
use App\Services\VaultCrypto;
use App\Services\VaultService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * Login em etapas: senha → frase de segurança → 2FA (TOTP) → passkey (opcional).
 * A sessão só é autenticada quando todas as etapas exigidas forem concluídas.
 */
class LoginController extends Controller
{
    private const STEPS = ['password' => 1, 'phrase' => 2, 'totp' => 3];

    public function __construct(private KeyVault $kv, private VaultService $vault)
    {
    }

    /* ------------------------------------------------------------ */

    private function pendingUser(Request $request, string $step): ?User
    {
        $s = $request->session();
        $started = (int) $s->get('login.started_at', 0);
        if (! $s->has('login.user_id') || $s->get('login.step') !== $step
            || $started < now()->subMinutes(config('vault.login_step_minutes'))->timestamp) {
            return null;
        }
        $user = User::find($s->get('login.user_id'));
        return ($user && $user->is_active && ! $user->isLocked()) ? $user : null;
    }

    private function restart(Request $request, ?string $message = null)
    {
        $request->session()->forget('login');
        $this->kv->clear();
        return redirect()->route('login')->withErrors(['email' => $message ?? __('auth.session_expired')]);
    }

    private function registerFailure(User $user, Request $request, string $stage): bool
    {
        $user->failed_attempts++;
        $locked = false;
        if ($user->failed_attempts >= config('vault.max_attempts')) {
            $user->locked_until = now()->addMinutes(config('vault.lock_minutes'));
            $user->failed_attempts = 0;
            $locked = true;
        }
        $user->save();
        AuditLog::record('login.fail', $user->id, null, ['stage' => $stage, 'locked' => $locked]);
        return $locked;
    }

    private function throttleKey(Request $request): string
    {
        return 'login:' . $request->ip();
    }

    private function tooManyAttempts(Request $request): bool
    {
        if (RateLimiter::tooManyAttempts($this->throttleKey($request), 10)) {
            return true;
        }
        RateLimiter::hit($this->throttleKey($request), 60);
        return false;
    }

    /* ------------------------------------------------------------ */
    /* Etapa 1: e-mail + senha                                       */
    /* ------------------------------------------------------------ */

    public function showLogin(Request $request)
    {
        if (User::count() === 0) {
            return redirect()->route('setup');
        }
        if (Auth::check() && $this->kv->unlocked()) {
            return redirect()->route('dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate(['email' => 'required|email|max:190', 'password' => 'required|string|max:500']);

        if ($this->tooManyAttempts($request)) {
            return back()->withErrors(['email' => __('auth.throttle_simple')])->onlyInput('email');
        }

        $user = User::where('email', Str::lower($request->input('email')))->first();

        if (! $user || ! $user->password) {
            Hash::check($request->input('password'), '$2y$12$' . str_repeat('a', 53)); // tempo constante
            AuditLog::record('login.fail', null, null, ['stage' => 'password', 'email' => Str::limit($request->input('email'), 60)]);
            return back()->withErrors(['email' => __('auth.failed')])->onlyInput('email');
        }

        if ($user->isLocked()) {
            return back()->withErrors(['email' => __('auth.locked', ['minutes' => (int) ceil(now()->diffInMinutes($user->locked_until, true)) ?: 1])])->onlyInput('email');
        }

        if (! Hash::check($request->input('password'), $user->password)) {
            $this->registerFailure($user, $request, 'password');
            return back()->withErrors(['email' => __('auth.failed')])->onlyInput('email');
        }

        if (! $user->is_active || ! $user->isActivated()) {
            return back()->withErrors(['email' => __('auth.inactive')])->onlyInput('email');
        }

        $request->session()->regenerate();
        $this->kv->begin();
        $this->kv->put('pwk', $this->vault->passwordKey($user, $request->input('password')));
        $request->session()->put('login', [
            'user_id' => $user->id,
            'step' => 'password',
            'started_at' => now()->timestamp,
        ]);

        return redirect()->route('login.phrase');
    }

    /* ------------------------------------------------------------ */
    /* Etapa 2: frase de segurança                                   */
    /* ------------------------------------------------------------ */

    public function showPhrase(Request $request)
    {
        $user = $this->pendingUser($request, 'password');
        if (! $user) {
            return $this->restart($request);
        }
        return view('auth.phrase', ['user' => $user]);
    }

    public function phrase(Request $request)
    {
        $user = $this->pendingUser($request, 'password');
        if (! $user) {
            return $this->restart($request);
        }
        $request->validate(['phrase' => 'required|string|max:500']);

        $phrase = VaultCrypto::normalizePhrase($request->input('phrase'));
        $pwk = $this->kv->get('pwk');
        $kp = ($pwk && Hash::check($phrase, (string) $user->phrase_hash))
            ? $this->vault->unlockWithPhrase($user, $pwk, $phrase)
            : null;

        if (! $kp) {
            if ($this->registerFailure($user, $request, 'phrase')) {
                return $this->restart($request, __('auth.locked', ['minutes' => config('vault.lock_minutes')]));
            }
            return back()->withErrors(['phrase' => __('auth.phrase_failed')]);
        }

        $this->kv->forget('pwk');
        $this->kv->put('kp', $kp);
        $request->session()->put('login.step', 'phrase');

        return $user->hasTwoFactor()
            ? redirect()->route('login.2fa')
            : redirect()->route('login.2fa.setup');
    }

    /* ------------------------------------------------------------ */
    /* Etapa 3: 2FA                                                  */
    /* ------------------------------------------------------------ */

    public function showTwoFactor(Request $request)
    {
        $user = $this->pendingUser($request, 'phrase');
        if (! $user || ! $user->hasTwoFactor()) {
            return $this->restart($request);
        }
        return view('auth.two-factor');
    }

    public function twoFactor(Request $request)
    {
        $user = $this->pendingUser($request, 'phrase');
        if (! $user || ! $user->hasTwoFactor()) {
            return $this->restart($request);
        }
        $request->validate(['code' => 'required|string|max:32']);
        $code = trim($request->input('code'));
        $ok = false;

        $step = Totp::verify($user->two_factor_secret, $code, $user->totp_last_step);
        if ($step !== null) {
            $user->totp_last_step = $step;
            $user->save();
            $ok = true;
        } elseif (strlen(preg_replace('/[^A-Za-z0-9]/', '', $code)) >= 10) {
            $ok = $this->consumeRecoveryCode($user, $code);
            if ($ok) {
                AuditLog::record('2fa.recovery_code_used', $user->id);
            }
        }

        if (! $ok) {
            if ($this->registerFailure($user, $request, 'totp')) {
                return $this->restart($request, __('auth.locked', ['minutes' => config('vault.lock_minutes')]));
            }
            return back()->withErrors(['code' => __('auth.code_failed')]);
        }

        return $this->afterTotp($request, $user);
    }

    public static function hashRecoveryCode(string $code): string
    {
        return hash('sha256', strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code)));
    }

    public static function makeRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $raw = strtoupper(\App\Services\Base32::encode(random_bytes(7)));
            $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5, 5);
        }
        return $codes;
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $hash = self::hashRecoveryCode($code);
        $codes = $user->two_factor_recovery_codes ?? [];
        foreach ($codes as $i => $stored) {
            if (hash_equals($stored, $hash)) {
                unset($codes[$i]);
                $user->two_factor_recovery_codes = array_values($codes);
                $user->save();
                return true;
            }
        }
        return false;
    }

    /* Configuração obrigatória do 2FA (primeiro login) */

    public function showTwoFactorSetup(Request $request)
    {
        $user = $this->pendingUser($request, 'phrase');
        if (! $user || $user->hasTwoFactor()) {
            return $this->restart($request);
        }
        $secret = $request->session()->get('login.totp_secret');
        if (! $secret) {
            $secret = Totp::generateSecret();
            $request->session()->put('login.totp_secret', $secret);
        }
        return view('auth.two-factor-setup', [
            'secret' => $secret,
            'uri' => Totp::uri(config('vault.issuer'), $user->email, $secret),
        ]);
    }

    public function twoFactorSetup(Request $request)
    {
        $user = $this->pendingUser($request, 'phrase');
        $secret = $request->session()->get('login.totp_secret');
        if (! $user || $user->hasTwoFactor() || ! $secret) {
            return $this->restart($request);
        }
        $request->validate(['code' => 'required|string|max:10']);

        $step = Totp::verify($secret, $request->input('code'));
        if ($step === null) {
            return back()->withErrors(['code' => __('auth.code_failed')]);
        }

        $codes = self::makeRecoveryCodes();
        $user->two_factor_secret = $secret;
        $user->two_factor_confirmed_at = now();
        $user->totp_last_step = $step;
        $user->two_factor_recovery_codes = array_map([self::class, 'hashRecoveryCode'], $codes);
        $user->save();
        AuditLog::record('2fa.enabled', $user->id);

        $request->session()->forget('login.totp_secret');
        $request->session()->put('login.step', 'totp');
        $request->session()->flash('recovery_codes', $codes);

        return redirect()->route('login.codes');
    }

    public function showCodes(Request $request)
    {
        $user = $this->pendingUser($request, 'totp');
        $codes = $request->session()->get('recovery_codes');
        if (! $user || ! $codes) {
            return $this->restart($request);
        }
        $request->session()->keep('recovery_codes');
        return view('auth.recovery-codes', ['codes' => $codes, 'continue' => route('login.codes.continue')]);
    }

    public function codesContinue(Request $request)
    {
        $user = $this->pendingUser($request, 'totp');
        if (! $user) {
            return $this->restart($request);
        }
        return $this->afterTotp($request, $user, true);
    }

    private function afterTotp(Request $request, User $user, bool $alreadyStepped = false)
    {
        if ($user->passkey_required && $user->passkeys()->exists()) {
            $request->session()->put('login.step', 'totp');
            return redirect()->route('login.passkey');
        }
        return $this->complete($request, $user);
    }

    /* ------------------------------------------------------------ */
    /* Etapa 4 (opcional): passkey                                   */
    /* ------------------------------------------------------------ */

    public function showPasskey(Request $request)
    {
        if (! $this->pendingUser($request, 'totp')) {
            return $this->restart($request);
        }
        return view('auth.passkey');
    }

    public function passkeyOptions(Request $request, Passkeys $passkeys)
    {
        $user = $this->pendingUser($request, 'totp');
        if (! $user) {
            return response()->json(['message' => __('auth.session_expired')], 419);
        }
        return response()->json($passkeys->loginOptions($request, $user));
    }

    public function passkeyVerify(Request $request, Passkeys $passkeys)
    {
        $user = $this->pendingUser($request, 'totp');
        if (! $user) {
            return response()->json(['message' => __('auth.session_expired')], 419);
        }
        try {
            $passkeys->verify($request, $user);
        } catch (Throwable $e) {
            $this->registerFailure($user, $request, 'passkey');
            return response()->json(['message' => __('auth.passkey_failed')], 422);
        }
        $this->complete($request, $user);
        return response()->json(['redirect' => redirect()->intended(route('dashboard'))->getTargetUrl()]);
    }

    /* ------------------------------------------------------------ */

    private function complete(Request $request, User $user)
    {
        $request->session()->forget('login');
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('vault_last_activity', time());

        $user->failed_attempts = 0;
        $user->locked_until = null;
        $user->last_login_at = now();
        $user->last_login_ip = $request->ip();
        $user->save();

        RateLimiter::clear($this->throttleKey($request));
        AuditLog::record('login.success', $user->id);

        session(['locale' => $user->locale]);

        try {
            $this->vault->heal($user);
        } catch (Throwable) {
            // não bloqueia o login
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        if (Auth::check()) {
            AuditLog::record('logout', Auth::id());
        }
        $this->kv->clear();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login')->with('status', __('auth.logged_out'));
    }
}
