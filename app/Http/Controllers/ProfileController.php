<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Auth\LoginController;
use App\Http\Middleware\SetLocale;
use App\Models\AuditLog;
use App\Models\Passkey;
use App\Services\KeyVault;
use App\Services\Passkeys;
use App\Services\Totp;
use App\Services\VaultCrypto;
use App\Services\VaultService;
use App\Support\CredentialRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Throwable;

class ProfileController extends Controller
{
    public function __construct(private VaultService $vault, private KeyVault $kv)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        return view('profile.index', [
            'user' => $user,
            'passkeys' => $user->passkeys()->latest()->get(),
            'codesLeft' => count($user->two_factor_recovery_codes ?? []),
            'activity' => AuditLog::where('user_id', $user->id)->latest('created_at')->limit(25)->get(),
            'locales' => SetLocale::SUPPORTED,
        ]);
    }

    public function updatePreferences(Request $request)
    {
        $v = $request->validate([
            'name' => 'required|string|max:120',
            'locale' => ['required', Rule::in(array_keys(SetLocale::SUPPORTED))],
            'theme' => ['required', Rule::in(['dark', 'light', 'auto'])],
            'rotation_days' => 'required|integer|min:7|max:3650',
            'access_review_days' => 'required|integer|min:7|max:3650',
        ]);
        $request->user()->fill($v)->save();
        session(['locale' => $v['locale']]);
        return back()->with('status', __('app.saved'));
    }

    public function theme(Request $request)
    {
        $v = $request->validate(['theme' => ['required', Rule::in(['dark', 'light', 'auto'])]]);
        $request->user()->forceFill(['theme' => $v['theme']])->save();
        return response()->json(['ok' => true]);
    }

    private function checkTotp(Request $request): bool
    {
        $user = $request->user();
        $step = Totp::verify($user->two_factor_secret, (string) $request->input('code'), $user->totp_last_step);
        if ($step === null) {
            return false;
        }
        $user->totp_last_step = $step;
        $user->save();
        return true;
    }

    public function changeCredentials(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'current_password' => 'required|string|max:200',
            'current_phrase' => 'required|string|max:300',
            'code' => 'required|string|max:10',
            'password' => CredentialRules::password(),
            'phrase' => CredentialRules::phrase(),
        ]);

        $okPass = Hash::check($request->input('current_password'), $user->password);
        $okPhrase = Hash::check(VaultCrypto::normalizePhrase($request->input('current_phrase')), $user->phrase_hash);
        if (! $okPass || ! $okPhrase) {
            AuditLog::record('credentials.change_fail', $user->id);
            return back()->withErrors(['current_password' => __('app.current_credentials_wrong')]);
        }
        if (! $this->checkTotp($request)) {
            return back()->withErrors(['code' => __('auth.code_failed')]);
        }

        $kp = $this->kv->keypair();
        $this->vault->wrapForCredentials($user, $kp, $request->input('password'), $request->input('phrase'));
        $user->save();
        AuditLog::record('credentials.changed', $user->id);
        return back()->with('status', __('app.credentials_changed'));
    }

    public function regenerateCodes(Request $request)
    {
        $request->validate(['code' => 'required|string|max:10']);
        if (! $this->checkTotp($request)) {
            return back()->withErrors(['code' => __('auth.code_failed')]);
        }
        $codes = LoginController::makeRecoveryCodes();
        $user = $request->user();
        $user->two_factor_recovery_codes = array_map([LoginController::class, 'hashRecoveryCode'], $codes);
        $user->save();
        AuditLog::record('2fa.codes_regenerated', $user->id);
        return view('auth.recovery-codes', ['codes' => $codes, 'continue' => route('profile'), 'inApp' => true]);
    }

    public function regenerateRecoveryKey(Request $request)
    {
        $request->validate(['code' => 'required|string|max:10']);
        if (! $this->checkTotp($request)) {
            return back()->withErrors(['code' => __('auth.code_failed')]);
        }
        $key = $this->vault->regenerateRecoveryKey($request->user(), $this->kv->keypair());
        AuditLog::record('recovery_key.regenerated', $request->user()->id);
        return view('auth.recovery-key', ['recoveryKey' => $key, 'inApp' => true]);
    }

    /* ---------------- Passkeys ---------------- */

    public function passkeyOptions(Request $request, Passkeys $passkeys)
    {
        return response()->json($passkeys->registerOptions($request, $request->user()));
    }

    public function passkeyStore(Request $request, Passkeys $passkeys)
    {
        $request->validate(['name' => 'required|string|max:80']);
        try {
            $passkeys->register($request, $request->user(), $request->input('name'));
        } catch (Throwable $e) {
            return response()->json(['message' => __('app.passkey_register_failed') . ' (' . $e->getMessage() . ')'], 422);
        }
        AuditLog::record('passkey.added', $request->user()->id);
        return response()->json(['ok' => true]);
    }

    public function passkeyDestroy(Request $request, Passkey $passkey)
    {
        abort_unless((int) $passkey->user_id === (int) $request->user()->id, 404);
        $passkey->delete();
        $user = $request->user();
        if (! $user->passkeys()->exists()) {
            $user->passkey_required = false;
            $user->save();
        }
        AuditLog::record('passkey.removed', $user->id);
        return back()->with('status', __('app.deleted'));
    }

    public function passkeyRequirement(Request $request)
    {
        $user = $request->user();
        $required = $request->boolean('required');
        if ($required && ! $user->passkeys()->exists()) {
            return back()->withErrors(['passkey' => __('app.passkey_needed')]);
        }
        $user->passkey_required = $required;
        $user->save();
        AuditLog::record($required ? 'passkey.required_on' : 'passkey.required_off', $user->id);
        return back()->with('status', __('app.saved'));
    }

    /* ---------------- Idioma (também para visitantes) ---------------- */

    public function locale(Request $request, string $locale)
    {
        abort_unless(array_key_exists($locale, SetLocale::SUPPORTED), 404);
        session(['locale' => $locale]);
        if ($request->user()) {
            $request->user()->forceFill(['locale' => $locale])->save();
        }
        return back();
    }
}
