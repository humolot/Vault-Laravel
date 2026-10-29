<?php

namespace App\Support;

use App\Services\VaultCrypto;
use Closure;
use Illuminate\Validation\Rules\Password;

class CredentialRules
{
    public static function password(): array
    {
        return [
            'required', 'string', 'max:200', 'confirmed',
            Password::min(config('vault.password_min', 12))->letters()->mixedCase()->numbers()->symbols(),
        ];
    }

    public static function phraseHint(): string
    {
        $minWords = (int) config('vault.phrase_min_words', 1);
        return $minWords > 1
            ? __('auth.phrase_rule', ['chars' => config('vault.phrase_min', 12), 'words' => $minWords])
            : __('auth.phrase_rule_chars', ['chars' => config('vault.phrase_min', 12)]);
    }

    public static function phrase(string $passwordField = 'password'): array
    {
        return [
            'required', 'string', 'max:300', 'confirmed',
            function (string $attribute, mixed $value, Closure $fail) use ($passwordField) {
                $phrase = VaultCrypto::normalizePhrase((string) $value);
                $minChars = (int) config('vault.phrase_min', 12);
                $minWords = (int) config('vault.phrase_min_words', 1);
                $words = array_filter(explode(' ', $phrase), fn ($w) => mb_strlen($w) >= 2);
                if (mb_strlen($phrase) < $minChars || count($words) < $minWords) {
                    $fail(self::phraseHint());
                }
                if ($phrase === VaultCrypto::normalizePhrase((string) request()->input($passwordField))) {
                    $fail(__('auth.phrase_not_password'));
                }
            },
        ];
    }
}
