{{-- novos campos: senha + frase de segurança --}}
<div class="mb-3">
    <label for="password" class="form-label">{{ __('auth.password') }}</label>
    @include('partials.password-input', ['name' => 'password', 'icon' => 'bi-key', 'meter' => true, 'generator' => true, 'required' => true])
    <div class="form-text">{{ __('auth.password_rule', ['min' => config('vault.password_min')]) }}</div>
</div>
<div class="mb-3">
    <label for="password_confirmation" class="form-label">{{ __('auth.password_confirm') }}</label>
    @include('partials.password-input', ['name' => 'password_confirmation', 'icon' => 'bi-key', 'required' => true])
</div>
<hr class="my-4">
<div class="mb-3">
    <label for="phrase" class="form-label">{{ __('auth.security_phrase') }}</label>
    @include('partials.password-input', ['name' => 'phrase', 'icon' => 'bi-chat-square-quote', 'autocomplete' => 'off', 'required' => true])
    <div class="form-text">{{ \App\Support\CredentialRules::phraseHint() }}</div>
</div>
<div class="mb-3">
    <label for="phrase_confirmation" class="form-label">{{ __('auth.phrase_confirm') }}</label>
    @include('partials.password-input', ['name' => 'phrase_confirmation', 'icon' => 'bi-chat-square-quote', 'autocomplete' => 'off', 'required' => true])
</div>
<div class="alert alert-info small d-flex gap-2"><i class="bi bi-info-circle"></i><div>{{ __('auth.credentials_info') }}</div></div>
