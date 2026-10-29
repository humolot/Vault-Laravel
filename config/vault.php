<?php

return [
    // Nome exibido como emissor no app autenticador (TOTP)
    'issuer' => env('VAULT_ISSUER', env('APP_NAME', 'GBX Vault')),

    // Argon2id para derivar a chave do cofre (senha + frase)
    'kdf_ops' => (int) env('VAULT_KDF_OPS', SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE),
    'kdf_mem' => (int) env('VAULT_KDF_MEM', SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE),

    // Bloqueio por inatividade (minutos) — o cofre trava e exige novo login completo
    'idle_minutes' => (int) env('VAULT_IDLE_MINUTES', 15),

    // Tempo máximo para completar as etapas do login (minutos)
    'login_step_minutes' => 10,

    // Força bruta
    'max_attempts' => (int) env('VAULT_MAX_ATTEMPTS', 5),
    'lock_minutes' => (int) env('VAULT_LOCK_MINUTES', 15),

    // Validade do convite (horas)
    'invite_hours' => (int) env('VAULT_INVITE_HOURS', 72),

    // Política mínima de senha / frase
    'password_min' => 12,
    'phrase_min' => (int) env('VAULT_PHRASE_MIN', 12),
    'phrase_min_words' => (int) env('VAULT_PHRASE_MIN_WORDS', 1),

    // Anexos
    'attachment_max_kb' => (int) env('VAULT_ATTACHMENT_MAX_KB', 10240),
    'attachment_mimes' => 'pdf,txt,png,jpg,jpeg,webp,gif,json,csv,zip,doc,docx,xls,xlsx,odt,ods,key,pem,asc,gpg',

    // Limpar área de transferência após (segundos)
    'clipboard_clear_seconds' => (int) env('VAULT_CLIPBOARD_SECONDS', 30),

    // Histórico de senhas mantido por item
    'password_history' => 5,

    // Tipos de item: chave => ícone Bootstrap Icons + campos exibidos
    'types' => [
        'login' => ['icon' => 'bi-key-fill', 'fields' => ['username', 'password', 'url', 'notes']],
        'recovery_codes' => ['icon' => 'bi-shield-lock-fill', 'fields' => ['username', 'url', 'secret', 'notes']],
        'seed_phrase' => ['icon' => 'bi-file-earmark-lock2-fill', 'fields' => ['username', 'url', 'secret', 'notes']],
        'phone' => ['icon' => 'bi-phone-fill', 'fields' => ['phone', 'carrier', 'used_for', 'notes']],
        'server' => ['icon' => 'bi-hdd-network-fill', 'fields' => ['host', 'port', 'username', 'password', 'notes']],
        'api_key' => ['icon' => 'bi-code-square', 'fields' => ['username', 'url', 'secret', 'notes']],
        'wifi' => ['icon' => 'bi-wifi', 'fields' => ['username', 'password', 'notes']],
        'note' => ['icon' => 'bi-journal-lock', 'fields' => ['secret', 'notes']],
        'other' => ['icon' => 'bi-box-seam', 'fields' => ['username', 'password', 'url', 'secret', 'notes']],
    ],

    // Tipos em que se sugere rotação periódica
    'rotatable_types' => ['login', 'server', 'api_key', 'wifi', 'other'],

    // Campos considerados sensíveis (só revelados sob demanda)
    'secret_fields' => ['password', 'secret'],
];
