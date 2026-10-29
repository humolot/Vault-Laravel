<?php

return [
    'array' => 'O campo :attribute deve ser uma lista.',
    'boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
    'confirmed' => 'A confirmação de :attribute não confere.',
    'email' => 'O campo :attribute deve ser um e-mail válido.',
    'exists' => 'O :attribute selecionado é inválido.',
    'file' => 'O campo :attribute deve ser um arquivo.',
    'in' => 'O :attribute selecionado é inválido.',
    'integer' => 'O campo :attribute deve ser um número inteiro.',
    'max' => [
        'array' => 'O campo :attribute não pode ter mais de :max itens.',
        'file' => 'O arquivo :attribute não pode ter mais de :max KB.',
        'numeric' => 'O campo :attribute não pode ser maior que :max.',
        'string' => 'O campo :attribute não pode ter mais de :max caracteres.',
    ],
    'mimes' => 'O arquivo :attribute deve ser do tipo: :values.',
    'min' => [
        'array' => 'O campo :attribute deve ter ao menos :min itens.',
        'file' => 'O arquivo :attribute deve ter ao menos :min KB.',
        'numeric' => 'O campo :attribute deve ser no mínimo :min.',
        'string' => 'O campo :attribute deve ter ao menos :min caracteres.',
    ],
    'password' => [
        'letters' => 'A :attribute deve conter ao menos uma letra.',
        'mixed' => 'A :attribute deve conter letras maiúsculas e minúsculas.',
        'numbers' => 'A :attribute deve conter ao menos um número.',
        'symbols' => 'A :attribute deve conter ao menos um símbolo.',
        'uncompromised' => 'A :attribute informada apareceu em vazamentos de dados. Escolha outra.',
    ],
    'regex' => 'O formato de :attribute é inválido.',
    'required' => 'O campo :attribute é obrigatório.',
    'string' => 'O campo :attribute deve ser um texto.',
    'unique' => 'Este :attribute já está em uso.',
    'uploaded' => 'Falha ao enviar :attribute.',

    'attributes' => [
        'name' => 'nome', 'email' => 'e-mail', 'password' => 'senha', 'phrase' => 'frase de segurança',
        'current_password' => 'senha atual', 'current_phrase' => 'frase atual', 'code' => 'código 2FA',
        'recovery_key' => 'chave de recuperação', 'type' => 'tipo', 'tags' => 'etiqueta', 'username' => 'usuário',
        'url' => 'URL', 'secret' => 'segredo', 'notes' => 'observações', 'phone' => 'telefone', 'files' => 'arquivos',
        'files.*' => 'arquivo', 'rotation_days' => 'dias de rotação', 'target' => 'destinatário', 'permission' => 'permissão',
        'user_id' => 'usuário', 'role' => 'perfil', 'locale' => 'idioma', 'theme' => 'tema', 'color' => 'cor',
    ],
];
