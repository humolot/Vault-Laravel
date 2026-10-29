@extends('layouts.guest')
@section('title', __('auth.recover_title'))
@section('subtitle', __('auth.recover_subtitle'))
@section('card-class', 'guest-card-wide')
@section('content')
<form method="POST" action="{{ route('recover') }}" novalidate>
    @csrf
    <div class="mb-3">
        <label for="email" class="form-label">{{ __('auth.email') }}</label>
        <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" required autocomplete="username">
    </div>
    <div class="row g-3 mb-3">
        <div class="col-sm-8">
            <label for="recovery_key" class="form-label">{{ __('auth.recovery_key') }}</label>
            <input type="text" class="form-control font-mono" id="recovery_key" name="recovery_key" placeholder="XXXXX-XXXXX-…" required autocomplete="off" spellcheck="false">
        </div>
        <div class="col-sm-4">
            <label for="code" class="form-label">{{ __('auth.auth_code') }}</label>
            <input type="text" class="form-control font-mono text-center" id="code" name="code" inputmode="numeric" maxlength="6" required autocomplete="one-time-code">
        </div>
    </div>
    <hr class="my-4">
    <h2 class="h6 mb-3">{{ __('auth.new_credentials') }}</h2>
    @include('auth.credentials-fields')
    <button class="btn btn-accent w-100 btn-lg">{{ __('auth.recover_button') }}</button>
</form>
<div class="text-center mt-3"><a href="{{ route('login') }}" class="small link-secondary"><i class="bi bi-arrow-left"></i> {{ __('auth.back_login') }}</a></div>
<p class="small text-body-secondary mt-3 mb-0">{{ __('auth.recover_no_key') }}</p>
@endsection
