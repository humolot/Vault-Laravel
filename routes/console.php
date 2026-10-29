<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

Artisan::command('vault:unlock {email}', function (string $email) {
    $user = User::where('email', strtolower($email))->first();
    if (! $user) {
        $this->error('Usuário não encontrado.');
        return 1;
    }
    $user->forceFill(['locked_until' => null, 'failed_attempts' => 0])->save();
    $this->info("Conta {$user->email} desbloqueada.");
})->purpose('Remove o bloqueio por tentativas de um usuário');

Artisan::command('vault:reset-2fa {email}', function (string $email) {
    $user = User::where('email', strtolower($email))->first();
    if (! $user) {
        $this->error('Usuário não encontrado.');
        return 1;
    }
    $user->forceFill([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
        'two_factor_recovery_codes' => null,
        'totp_last_step' => null,
    ])->save();
    $this->info("2FA de {$user->email} redefinido. Será configurado novamente no próximo login (senha + frase continuam exigidas).");
})->purpose('Redefine o 2FA de um usuário (emergência)');

Artisan::command('vault:make-admin {email}', function (string $email) {
    $user = User::where('email', strtolower($email))->first();
    if (! $user) {
        $this->error('Usuário não encontrado.');
        return 1;
    }
    $user->forceFill(['role' => 'admin'])->save();
    $this->info("{$user->email} agora é administrador.");
})->purpose('Promove um usuário a administrador');
