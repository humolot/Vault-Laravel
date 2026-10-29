<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Throwable;

/**
 * Guarda segredos da sessão (par de chaves do usuário, chave parcial do login)
 * cifrados no servidor com uma chave que vive apenas num cookie httpOnly do navegador.
 * Um dump do banco/sessões sem o cookie do navegador não revela o cofre.
 */
class KeyVault
{
    public const COOKIE = 'gbx_vk';

    private ?string $cookieKey = null;

    private array $cache = [];

    public function __construct(private Request $request)
    {
    }

    private function key(bool $create = false): ?string
    {
        if ($this->cookieKey !== null) {
            return $this->cookieKey;
        }
        $raw = $this->request->cookie(self::COOKIE);
        $bin = is_string($raw) ? base64_decode($raw, true) : false;
        if ($bin !== false && strlen($bin) === VaultCrypto::KEY_BYTES) {
            return $this->cookieKey = $bin;
        }
        if (! $create) {
            return null;
        }
        $this->cookieKey = VaultCrypto::randomKey();
        Cookie::queue(Cookie::make(
            self::COOKIE,
            base64_encode($this->cookieKey),
            0, // cookie de sessão do navegador
            config('session.path'),
            config('session.domain'),
            config('session.secure'),
            true,
            false,
            'strict'
        ));
        return $this->cookieKey;
    }

    /** Inicia um novo contexto (novo login): troca a chave do cookie. */
    public function begin(): void
    {
        $this->cookieKey = null;
        $this->cache = [];
        $this->request->session()->forget('vault');
        $this->request->cookies->remove(self::COOKIE);
        $this->key(true);
    }

    public function put(string $name, string $secret): void
    {
        $key = $this->key(true);
        $this->request->session()->put('vault.' . $name, VaultCrypto::encrypt($secret, $key));
        $this->cache[$name] = $secret;
    }

    public function get(string $name): ?string
    {
        if (array_key_exists($name, $this->cache)) {
            return $this->cache[$name];
        }
        $blob = $this->request->session()->get('vault.' . $name);
        $key = $this->key();
        if (! $blob || ! $key) {
            return null;
        }
        try {
            return $this->cache[$name] = VaultCrypto::decrypt($blob, $key);
        } catch (Throwable) {
            return null;
        }
    }

    public function forget(string $name): void
    {
        unset($this->cache[$name]);
        $this->request->session()->forget('vault.' . $name);
    }

    public function keypair(): ?string
    {
        return $this->get('kp');
    }

    public function unlocked(): bool
    {
        return $this->keypair() !== null;
    }

    public function clear(): void
    {
        $this->cache = [];
        $this->cookieKey = null;
        $this->request->session()->forget('vault');
        Cookie::queue(Cookie::forget(self::COOKIE, config('session.path'), config('session.domain')));
    }
}
