<?php

return [
    'array' => 'El campo :attribute debe ser una lista.',
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'email' => 'El campo :attribute debe ser un correo válido.',
    'exists' => 'El :attribute seleccionado no es válido.',
    'file' => 'El campo :attribute debe ser un archivo.',
    'in' => 'El :attribute seleccionado no es válido.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'max' => [
        'array' => 'El campo :attribute no puede tener más de :max elementos.',
        'file' => 'El archivo :attribute no puede superar :max KB.',
        'numeric' => 'El campo :attribute no puede ser mayor que :max.',
        'string' => 'El campo :attribute no puede tener más de :max caracteres.',
    ],
    'mimes' => 'El archivo :attribute debe ser de tipo: :values.',
    'min' => [
        'array' => 'El campo :attribute debe tener al menos :min elementos.',
        'file' => 'El archivo :attribute debe tener al menos :min KB.',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'password' => [
        'letters' => 'La :attribute debe contener al menos una letra.',
        'mixed' => 'La :attribute debe contener mayúsculas y minúsculas.',
        'numbers' => 'La :attribute debe contener al menos un número.',
        'symbols' => 'La :attribute debe contener al menos un símbolo.',
        'uncompromised' => 'La :attribute apareció en filtraciones de datos. Elija otra.',
    ],
    'regex' => 'El formato de :attribute no es válido.',
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser texto.',
    'unique' => 'Este :attribute ya está en uso.',
    'uploaded' => 'No se pudo subir :attribute.',

    'attributes' => [
        'name' => 'nombre', 'email' => 'correo', 'password' => 'contraseña', 'phrase' => 'frase de seguridad',
        'current_password' => 'contraseña actual', 'current_phrase' => 'frase actual', 'code' => 'código 2FA',
        'recovery_key' => 'clave de recuperación', 'type' => 'tipo', 'tags' => 'etiqueta', 'username' => 'usuario',
        'url' => 'URL', 'secret' => 'secreto', 'notes' => 'observaciones', 'phone' => 'teléfono', 'files' => 'archivos',
        'files.*' => 'archivo', 'rotation_days' => 'días de rotación', 'target' => 'destinatario', 'permission' => 'permiso',
        'user_id' => 'usuario', 'role' => 'perfil', 'locale' => 'idioma', 'theme' => 'tema', 'color' => 'color',
    ],
];
