@extends('layouts.app')
@section('title', __('app.profile'))
@section('content')
<div class="page-head">
    <div>
        <h1 class="page-title">{{ __('app.profile') }}</h1>
        <p class="page-sub">{{ $user->email }}</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-6">
        {{-- Preferências --}}
        <div class="card card-pad mb-4">
            <h2 class="h6 mb-3"><i class="bi bi-sliders me-1"></i>{{ __('app.preferences') }}</h2>
            <form method="POST" action="{{ route('profile.update') }}">
                @csrf @method('PUT')
                <div class="mb-3"><label class="form-label">{{ __('auth.name') }}</label><input class="form-control" name="name" value="{{ old('name', $user->name) }}" required maxlength="120"></div>
                <div class="row g-3 mb-3">
                    <div class="col-6"><label class="form-label">{{ __('app.language') }}</label>
                        <select class="form-select" name="locale">@foreach ($locales as $c => $l)<option value="{{ $c }}" @selected($user->locale === $c)>{{ $l }}</option>@endforeach</select></div>
                    <div class="col-6"><label class="form-label">{{ __('app.theme') }}</label>
                        <select class="form-select" name="theme" data-theme-select>
                            <option value="dark" @selected($user->theme === 'dark')>{{ __('app.theme_dark') }}</option>
                            <option value="light" @selected($user->theme === 'light')>{{ __('app.theme_light') }}</option>
                            <option value="auto" @selected($user->theme === 'auto')>{{ __('app.theme_auto') }}</option>
                        </select></div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6"><label class="form-label">{{ __('app.rotation_default') }}</label>
                        <div class="input-group"><input type="number" class="form-control" name="rotation_days" min="7" max="3650" value="{{ $user->rotation_days }}"><span class="input-group-text">{{ __('app.days') }}</span></div></div>
                    <div class="col-6"><label class="form-label">{{ __('app.access_review_default') }}</label>
                        <div class="input-group"><input type="number" class="form-control" name="access_review_days" min="7" max="3650" value="{{ $user->access_review_days }}"><span class="input-group-text">{{ __('app.days') }}</span></div></div>
                </div>
                <p class="form-text">{{ __('app.rotation_explain') }}</p>
                <button class="btn btn-accent">{{ __('app.save') }}</button>
            </form>
        </div>

        {{-- Credenciais --}}
        <div class="card card-pad mb-4">
            <h2 class="h6 mb-1"><i class="bi bi-key me-1"></i>{{ __('app.change_credentials') }}</h2>
            <p class="small text-body-secondary">{{ __('app.change_credentials_help') }}</p>
            <form method="POST" action="{{ route('profile.credentials') }}" novalidate>
                @csrf @method('PUT')
                <div class="row g-3 mb-3">
                    <div class="col-md-6"><label class="form-label">{{ __('app.current_password') }}</label>
                        @include('partials.password-input', ['name' => 'current_password', 'autocomplete' => 'current-password', 'required' => true])</div>
                    <div class="col-md-6"><label class="form-label">{{ __('app.current_phrase') }}</label>
                        @include('partials.password-input', ['name' => 'current_phrase', 'autocomplete' => 'off', 'required' => true])</div>
                </div>
                <hr>
                @include('auth.credentials-fields')
                <div class="mb-3" style="max-width: 220px">
                    <label class="form-label">{{ __('auth.auth_code') }}</label>
                    <input type="text" class="form-control font-mono text-center" name="code" inputmode="numeric" maxlength="6" required autocomplete="one-time-code">
                </div>
                <button class="btn btn-accent">{{ __('app.update_credentials') }}</button>
            </form>
        </div>
    </div>

    <div class="col-xl-6">
        {{-- 2FA --}}
        <div class="card card-pad mb-4">
            <h2 class="h6 mb-3"><i class="bi bi-shield-check me-1"></i>{{ __('app.two_factor') }}</h2>
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>{{ __('app.active') }}</span>
                <span class="small text-body-secondary">{{ __('app.since', ['date' => $user->two_factor_confirmed_at?->format('d/m/Y')]) }} · {{ trans_choice('app.codes_left', $codesLeft, ['count' => $codesLeft]) }}</span>
            </div>
            <form method="POST" action="{{ route('profile.codes') }}" class="d-flex gap-2">
                @csrf
                <input type="text" class="form-control font-mono text-center" name="code" inputmode="numeric" maxlength="6" placeholder="{{ __('auth.auth_code') }}" required style="max-width: 160px" autocomplete="one-time-code">
                <button class="btn btn-outline-secondary">{{ __('app.regenerate_codes') }}</button>
            </form>
        </div>

        {{-- Passkeys --}}
        <div class="card card-pad mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 mb-0"><i class="bi bi-fingerprint me-1"></i>{{ __('app.passkeys') }} <span class="badge text-bg-secondary ms-1">{{ __('app.optional') }}</span></h2>
                <button type="button" class="btn btn-sm btn-accent" id="addPasskey" data-options="{{ route('passkeys.options') }}" data-store="{{ route('passkeys.store') }}">
                    <i class="bi bi-plus-lg me-1"></i>{{ __('app.add_passkey') }}</button>
            </div>
            <p class="small text-body-secondary">{{ __('app.passkeys_help') }}</p>
            <div class="alert alert-danger small d-none" id="passkeyError"></div>
            @if ($passkeys->isNotEmpty())
                <ul class="list-group list-group-flush mb-3">
                    @foreach ($passkeys as $pk)
                        <li class="list-group-item px-0 d-flex align-items-center gap-3">
                            <i class="bi bi-key-fill text-accent fs-5"></i>
                            <div class="flex-grow-1">
                                <div>{{ $pk->name }}</div>
                                <div class="small text-body-secondary">{{ __('app.added_on', ['date' => $pk->created_at->format('d/m/Y')]) }} · {{ __('app.last_used') }}: {{ $pk->last_used_at?->format('d/m/Y H:i') ?? '—' }}</div>
                            </div>
                            <form method="POST" action="{{ route('passkeys.destroy', $pk) }}" data-confirm="{{ __('app.confirm_delete') }}">@csrf @method('DELETE')
                                <button class="btn btn-icon text-danger"><i class="bi bi-trash3"></i></button></form>
                        </li>
                    @endforeach
                </ul>
                <form method="POST" action="{{ route('passkeys.requirement') }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="required" value="{{ $user->passkey_required ? 0 : 1 }}">
                    <div class="d-flex align-items-center justify-content-between gap-2 p-3 rounded-3 bg-body-tertiary">
                        <div>
                            <div class="fw-medium">{{ __('app.require_passkey') }}</div>
                            <div class="small text-body-secondary">{{ __('app.require_passkey_help') }}</div>
                        </div>
                        <button class="btn btn-sm {{ $user->passkey_required ? 'btn-success' : 'btn-outline-secondary' }}">
                            {{ $user->passkey_required ? __('app.enabled') : __('app.enable') }}</button>
                    </div>
                </form>
            @endif
        </div>

        {{-- Chave de recuperação --}}
        <div class="card card-pad mb-4">
            <h2 class="h6 mb-1"><i class="bi bi-life-preserver me-1"></i>{{ __('auth.recovery_key') }}</h2>
            <p class="small text-body-secondary">{{ __('app.recovery_key_help') }}</p>
            <form method="POST" action="{{ route('profile.recovery-key') }}" class="d-flex gap-2" data-confirm="{{ __('app.confirm_new_recovery_key') }}">
                @csrf
                <input type="text" class="form-control font-mono text-center" name="code" inputmode="numeric" maxlength="6" placeholder="{{ __('auth.auth_code') }}" required style="max-width: 160px" autocomplete="one-time-code">
                <button class="btn btn-outline-secondary">{{ __('app.new_recovery_key') }}</button>
            </form>
        </div>

        {{-- Atividade --}}
        <div class="card mb-4">
            <div class="card-header"><strong><i class="bi bi-activity me-1"></i>{{ __('app.recent_activity') }}</strong></div>
            <ul class="list-group list-group-flush small activity-list">
                @forelse ($activity as $a)
                    <li class="list-group-item d-flex justify-content-between gap-2">
                        <span><span class="badge audit-badge audit-{{ \Illuminate\Support\Str::before($a->action, '.') }}">{{ $a->action }}</span></span>
                        <span class="text-body-secondary text-nowrap">{{ $a->created_at?->format('d/m H:i') }} · {{ $a->ip }}</span>
                    </li>
                @empty
                    <li class="list-group-item text-body-secondary">{{ __('app.no_results') }}</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
