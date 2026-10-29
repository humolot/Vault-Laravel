@extends('layouts.guest')
@section('title', __('auth.step_passkey'))
@section('subtitle', __('auth.passkey_subtitle'))
@section('steps')@include('partials.login-steps', ['current' => 'passkey', 'passkey' => true])@endsection
@section('content')
<div class="text-center">
    <div class="passkey-icon mb-3"><i class="bi bi-fingerprint"></i></div>
    <p class="small text-body-secondary">{{ __('auth.passkey_hint') }}</p>
    <div class="alert alert-danger small d-none" id="passkeyError"></div>
    <button type="button" class="btn btn-accent w-100 btn-lg" id="passkeyLogin"
            data-options="{{ route('login.passkey.options') }}" data-verify="{{ route('login.passkey.verify') }}">
        <i class="bi bi-fingerprint me-1"></i> {{ __('auth.use_passkey') }}
    </button>
</div>
<form method="POST" action="{{ route('logout') }}" class="text-center mt-3">@csrf<button class="btn btn-link btn-sm link-secondary">{{ __('auth.cancel') }}</button></form>
@endsection
