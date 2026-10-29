@extends('layouts.guest')
@section('title', __('auth.step_2fa'))
@section('subtitle', __('auth.two_factor_subtitle'))
@section('steps')@include('partials.login-steps', ['current' => 'totp'])@endsection
@section('content')
<form method="POST" action="{{ route('login.2fa') }}" novalidate>
    @csrf
    <div class="mb-3">
        <label for="code" class="form-label">{{ __('auth.auth_code') }}</label>
        <input type="text" class="form-control form-control-lg text-center font-mono otp-input" id="code" name="code"
               inputmode="numeric" autocomplete="one-time-code" maxlength="32" required autofocus placeholder="000000">
    </div>
    <p class="form-text mb-4">{{ __('auth.two_factor_hint') }}</p>
    <button class="btn btn-accent w-100 btn-lg">{{ __('auth.verify') }} <i class="bi bi-shield-check ms-1"></i></button>
</form>
<form method="POST" action="{{ route('logout') }}" class="text-center mt-3">@csrf<button class="btn btn-link btn-sm link-secondary">{{ __('auth.cancel') }}</button></form>
@endsection
