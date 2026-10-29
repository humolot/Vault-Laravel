<?php

namespace App\Services;

use App\Models\Passkey;
use App\Models\User;
use Illuminate\Http\Request;
use lbuchs\WebAuthn\Binary\ByteBuffer;
use lbuchs\WebAuthn\WebAuthn;
use RuntimeException;

class Passkeys
{
    public static function b64urlEncode(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function b64Decode(?string $value): string
    {
        $value = (string) $value;
        $value = strtr($value, '-_', '+/');
        $pad = strlen($value) % 4;
        if ($pad) {
            $value .= str_repeat('=', 4 - $pad);
        }
        $bin = base64_decode($value, true);
        if ($bin === false) {
            throw new RuntimeException('Dados inválidos.');
        }
        return $bin;
    }

    private function make(Request $request): WebAuthn
    {
        return new WebAuthn(config('app.name'), $request->getHost());
    }

    private function userHandle(User $user): string
    {
        return hash('sha256', 'gbx-vault-user-' . $user->id . '-' . config('app.key'), true);
    }

    public function registerOptions(Request $request, User $user): object
    {
        $wa = $this->make($request);
        $exclude = $user->passkeys()->pluck('credential_id')->map(fn ($id) => self::b64Decode($id))->all();
        $args = $wa->getCreateArgs($this->userHandle($user), $user->email, $user->name, 90, 'preferred', true, null, $exclude);
        $request->session()->put('webauthn.register', base64_encode($wa->getChallenge()->getBinaryString()));
        return $args;
    }

    public function register(Request $request, User $user, string $name): Passkey
    {
        $challenge = $request->session()->pull('webauthn.register');
        if (! $challenge) {
            throw new RuntimeException('Desafio expirado.');
        }
        $wa = $this->make($request);
        $data = $wa->processCreate(
            self::b64Decode($request->input('clientDataJSON')),
            self::b64Decode($request->input('attestationObject')),
            new ByteBuffer(base64_decode($challenge)),
            true,
            true,
            false
        );

        $pk = new Passkey();
        $pk->user_id = $user->id;
        $pk->name = $name;
        $pk->credential_id = self::b64urlEncode($data->credentialId);
        $pk->public_key = $data->credentialPublicKey;
        $pk->sign_count = (int) ($data->signatureCounter ?? 0);
        $pk->save();
        return $pk;
    }

    public function loginOptions(Request $request, User $user): object
    {
        $wa = $this->make($request);
        $ids = $user->passkeys()->pluck('credential_id')->map(fn ($id) => self::b64Decode($id))->all();
        $args = $wa->getGetArgs($ids, 90, true, true, true, true, true, true);
        $request->session()->put('webauthn.login', base64_encode($wa->getChallenge()->getBinaryString()));
        return $args;
    }

    public function verify(Request $request, User $user): Passkey
    {
        $challenge = $request->session()->pull('webauthn.login');
        if (! $challenge) {
            throw new RuntimeException('Desafio expirado.');
        }
        $credId = self::b64urlEncode(self::b64Decode($request->input('id')));
        $pk = $user->passkeys()->where('credential_id', $credId)->first();
        if (! $pk) {
            throw new RuntimeException('Passkey não reconhecida.');
        }
        $wa = $this->make($request);
        $wa->processGet(
            self::b64Decode($request->input('clientDataJSON')),
            self::b64Decode($request->input('authenticatorData')),
            self::b64Decode($request->input('signature')),
            $pk->public_key,
            new ByteBuffer(base64_decode($challenge)),
            (int) $pk->sign_count,
            true
        );
        $pk->sign_count = (int) ($wa->getSignatureCounter() ?? $pk->sign_count);
        $pk->last_used_at = now();
        $pk->save();
        return $pk;
    }
}
