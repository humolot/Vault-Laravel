@extends('layouts.guest')
@section('title', __('auth.invite_title'))
@section('subtitle', __('auth.invite_subtitle', ['name' => $user->name]))
@section('card-class', 'guest-card-wide')
@section('content')
<form method="POST" action="{{ route('invite.show', $token) }}" novalidate>
    @csrf
    <div class="mb-3">
        <label class="form-label">{{ __('auth.email') }}</label>
        <input type="email" class="form-control" value="{{ $user->email }}" readonly autocomplete="username">
    </div>
    @include('auth.credentials-fields')
    <button class="btn btn-accent w-100 btn-lg">{{ __('auth.activate_account') }}</button>
</form>
@endsection
