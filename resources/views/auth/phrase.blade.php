@extends('layouts.guest')
@section('title', __('auth.step_phrase'))
@section('subtitle', __('auth.phrase_subtitle', ['name' => $user->name]))
@section('steps')@include('partials.login-steps', ['current' => 'phrase'])@endsection
@section('content')
<form method="POST" action="{{ route('login.phrase') }}" novalidate>
    @csrf
    <div class="mb-2">
        <label for="phrase" class="form-label">{{ __('auth.security_phrase') }}</label>
        @include('partials.password-input', ['name' => 'phrase', 'icon' => 'bi-chat-square-quote', 'autocomplete' => 'off', 'required' => true])
    </div>
    <p class="form-text mb-4"><i class="bi bi-info-circle"></i> {{ __('auth.phrase_hint') }}</p>
    <button class="btn btn-accent w-100 btn-lg">{{ __('auth.unlock') }} <i class="bi bi-unlock ms-1"></i></button>
</form>
<form method="POST" action="{{ route('logout') }}" class="text-center mt-3">@csrf<button class="btn btn-link btn-sm link-secondary">{{ __('auth.cancel') }}</button></form>
@endsection
