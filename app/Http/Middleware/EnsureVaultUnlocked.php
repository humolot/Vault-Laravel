<?php

namespace App\Http\Middleware;

use App\Services\KeyVault;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Exige sessão autenticada + cofre destravado + atividade recente. */
class EnsureVaultUnlocked
{
    public function __construct(private KeyVault $kv)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $last = (int) $request->session()->get('vault_last_activity', 0);
        $idle = config('vault.idle_minutes', 15) * 60;

        $reason = null;
        if (! $user) {
            $reason = 'auth.session_expired';
        } elseif (! $user->is_active || ! $user->public_key) {
            $reason = 'auth.inactive';
        } elseif (! $this->kv->unlocked()) {
            $reason = 'auth.vault_locked';
        } elseif ($last && (time() - $last) > $idle) {
            $reason = 'auth.idle_locked';
        }

        if ($reason) {
            $this->kv->clear();
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            if ($request->expectsJson()) {
                return response()->json(['message' => __($reason), 'redirect' => route('login')], 401);
            }
            return redirect()->route('login')->withErrors(['email' => __($reason)]);
        }

        if (! $request->headers->has('X-Vault-Ping')) {
            $request->session()->put('vault_last_activity', time());
        }

        return $next($request);
    }
}
