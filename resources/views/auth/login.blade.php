@extends('layouts.guest')
@section('title', __('auth.sign_in'))
@section('subtitle', __('auth.sign_in_subtitle'))
@section('steps')@include('partials.login-steps', ['current' => 'password'])@endsection
@section('content')
<form method="POST" action="{{ route('login') }}" autocomplete="on" novalidate>
    @csrf
    <div class="mb-3">
        <label for="email" class="form-label">{{ __('auth.email') }}</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        </div>
    </div>
    <div class="mb-4">
        <label for="password" class="form-label">{{ __('auth.password') }}</label>
        @include('partials.password-input', ['name' => 'password', 'icon' => 'bi-key', 'autocomplete' => 'current-password', 'required' => true])
    </div>
    <button class="btn btn-accent w-100 btn-lg">{{ __('auth.continue') }} <i class="bi bi-arrow-right ms-1"></i></button>
</form>
<div class="text-center mt-3">
    <a href="{{ route('recover') }}" class="small link-secondary">{{ __('auth.forgot') }}</a>
</div>
@endsection
