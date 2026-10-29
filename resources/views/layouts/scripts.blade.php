<script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha384-1H217gwSVyLSIfaLxHbE7dRb3v4mYCKbpQvzx0cegeju1MVsGrX5xXxAvs/HgeFs" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
@php
    $gbxConfig = [
    'csrf' => csrf_token(),
    'idleMinutes' => (int) config('vault.idle_minutes'),
    'clipboardSeconds' => (int) config('vault.clipboard_clear_seconds'),
    'authenticated' => auth()->check(),
    'urls' => [
        'login' => route('login'),
        'ping' => auth()->check() ? route('ping') : null,
        'theme' => auth()->check() ? route('profile.theme') : null,
        'logout' => route('logout'),
    ],
    'i18n' => [
        'copied' => __('app.copied'),
        'clipboard_cleared' => __('app.clipboard_cleared'),
        'copy_failed' => __('app.copy_failed'),
        'error' => __('app.error_generic'),
        'idle_warning' => __('app.idle_warning'),
        'strength' => [__('app.strength_0'), __('app.strength_1'), __('app.strength_2'), __('app.strength_3'), __('app.strength_4')],
        'confirm' => __('app.confirm_action'),
        'cancel' => __('app.cancel'),
        'passkey_unsupported' => __('app.passkey_unsupported'),
        'passkey_name_prompt' => __('app.passkey_name_prompt'),
        'passkey_added' => __('app.passkey_added'),
        'hide' => __('app.hide'),
        'show' => __('app.show'),
    ],
];
@endphp
<script id="gbx-config" type="application/json">{!! json_encode($gbxConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
@stack('scripts')
<script src="{{ asset('assets/js/app.js') }}?v={{ filemtime(public_path('assets/js/app.js')) }}"></script>
