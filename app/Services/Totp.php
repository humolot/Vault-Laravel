<?php

namespace App\Services;

/** TOTP RFC 6238 (SHA1, 6 dígitos, 30 s) — compatível com Google Authenticator, Authy, 1Password, etc. */
class Totp
{
    public const PERIOD = 30;
    public const DIGITS = 6;

    public static function generateSecret(): string
    {
        return Base32::encode(random_bytes(20));
    }

    public static function codeAt(string $secret, int $timestep): string
    {
        $key = Base32::decode($secret);
        $hash = hash_hmac('sha1', pack('J', $timestep), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Verifica o código. Retorna o timestep aceito (para proteção contra replay) ou null.
     */
    public static function verify(string $secret, string $code, ?int $lastUsedStep = null, int $window = 1, ?int $now = null): ?int
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== self::DIGITS) {
            return null;
        }
        $current = intdiv($now ?? time(), self::PERIOD);
        for ($i = -$window; $i <= $window; $i++) {
            $step = $current + $i;
            if ($lastUsedStep !== null && $step <= $lastUsedStep) {
                continue;
            }
            if (hash_equals(self::codeAt($secret, $step), $code)) {
                return $step;
            }
        }
        return null;
    }

    public static function uri(string $issuer, string $account, string $secret): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($issuer),
            rawurlencode($account),
            $secret,
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD
        );
    }
}
