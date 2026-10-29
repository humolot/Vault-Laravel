@extends('layouts.guest')
@section('title', __('auth.setup_title'))
@section('subtitle', __('auth.setup_subtitle'))
@section('card-class', 'guest-card-wide')
@section('content')
<form method="POST" action="{{ route('setup') }}" novalidate>
    @csrf
    <div class="row g-3 mb-3">
        <div class="col-sm-6">
            <label for="name" class="form-label">{{ __('auth.name') }}</label>
            <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" required>
        </div>
        <div class="col-sm-6">
            <label for="email" class="form-label">{{ __('auth.email') }}</label>
            <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" required autocomplete="username">
        </div>
    </div>
    @include('auth.credentials-fields')
    <button class="btn btn-accent w-100 btn-lg">{{ __('auth.create_admin') }}</button>
</form>
@endsection
