<?php

namespace App\Services;

use RuntimeException;

/**
 * Primitivas criptográficas do cofre (libsodium).
 *
 * Modelo:
 *  - Cada usuário tem um par de chaves X25519 (crypto_box). A chave privada fica
 *    cifrada no banco com uma KEK derivada (Argon2id) de senha + frase de segurança.
 *  - Cada item tem uma chave simétrica aleatória (XSalsa20-Poly1305). Essa chave é
 *    "selada" (crypto_box_seal) para cada usuário ou grupo que tem acesso.
 *  - Cada grupo tem um par de chaves próprio; a chave privada do grupo é selada
 *    para cada membro.
 */
class VaultCrypto
{
    public const KEY_BYTES = SODIUM_CRYPTO_SECRETBOX_KEYBYTES;

    public static function randomKey(): string
    {
        return random_bytes(self::KEY_BYTES);
    }

    public static function randomSalt(): string
    {
        return random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
    }

    /** Deriva chave de 32 bytes a partir de um segredo humano (Argon2id). */
    public static function deriveKey(string $secret, string $salt): string
    {
        return sodium_crypto_pwhash(
            self::KEY_BYTES,
            $secret,
            $salt,
            (int) config('vault.kdf_ops', SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE),
            (int) config('vault.kdf_mem', SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE),
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );
    }

    /** Combina duas chaves derivadas numa KEK única. */
    public static function combineKeys(string $a, string $b): string
    {
        return sodium_crypto_generichash($a . $b, 'gbx-vault-kek-v1', self::KEY_BYTES);
    }

    /** Chave a partir de material de alta entropia (ex.: chave de recuperação). */
    public static function keyFromHighEntropy(string $material): string
    {
        return sodium_crypto_generichash($material, 'gbx-vault-rec-v1', self::KEY_BYTES);
    }

    public static function normalizePhrase(string $phrase): string
    {
        $phrase = trim(preg_replace('/\s+/u', ' ', $phrase) ?? '');
        return \Normalizer::isNormalized($phrase) ? $phrase : (\Normalizer::normalize($phrase) ?: $phrase);
    }

    /* ---------------- cifra simétrica ---------------- */

    public static function encrypt(string $plain, string $key): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $key));
    }

    public static function decrypt(string $blob, string $key): string
    {
        $raw = base64_decode($blob, true);
        if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            throw new RuntimeException('Blob inválido.');
        }
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
        if ($plain === false) {
            throw new RuntimeException('Falha ao decifrar.');
        }
        return $plain;
    }

    /** Versão binária (sem base64) para arquivos. */
    public static function encryptRaw(string $plain, string $key): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return $nonce . sodium_crypto_secretbox($plain, $nonce, $key);
    }

    public static function decryptRaw(string $raw, string $key): string
    {
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $key);
        if ($plain === false) {
            throw new RuntimeException('Falha ao decifrar arquivo.');
        }
        return $plain;
    }

    public static function encryptJson(array $data, string $key): string
    {
        return self::encrypt(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $key);
    }

    public static function decryptJson(string $blob, string $key): array
    {
        return json_decode(self::decrypt($blob, $key), true, 512, JSON_THROW_ON_ERROR);
    }

    /* ---------------- pares de chaves ---------------- */

    /** @return array{0:string,1:string} [publicKeyBase64, keypairBinary] */
    public static function generateKeypair(): array
    {
        $kp = sodium_crypto_box_keypair();
        return [base64_encode(sodium_crypto_box_publickey($kp)), $kp];
    }

    public static function seal(string $plain, string $publicKeyB64): string
    {
        $pk = base64_decode($publicKeyB64, true);
        if ($pk === false || strlen($pk) !== SODIUM_CRYPTO_BOX_PUBLICKEYBYTES) {
            throw new RuntimeException('Chave pública inválida.');
        }
        return base64_encode(sodium_crypto_box_seal($plain, $pk));
    }

    public static function sealOpen(string $blobB64, string $keypair): string
    {
        $raw = base64_decode($blobB64, true);
        $plain = $raw === false ? false : sodium_crypto_box_seal_open($raw, $keypair);
        if ($plain === false) {
            throw new RuntimeException('Falha ao abrir chave selada.');
        }
        return $plain;
    }

    /* ---------------- chave de recuperação ---------------- */

    /** Gera chave de recuperação legível: 8 blocos de 5 caracteres base32. */
    public static function generateRecoveryKey(): string
    {
        $raw = Base32::encode(random_bytes(25)); // 40 caracteres
        return implode('-', str_split($raw, 5));
    }

    public static function normalizeRecoveryKey(string $key): string
    {
        return strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $key) ?? '');
    }
}
