@extends('layouts.guest')
@section('title', __('auth.setup_2fa'))
@section('subtitle', __('auth.setup_2fa_subtitle'))
@section('steps')@include('partials.login-steps', ['current' => 'totp'])@endsection
@section('content')
<div class="alert alert-warning small d-flex gap-2"><i class="bi bi-shield-exclamation"></i><div>{{ __('auth.setup_2fa_required') }}</div></div>
<ol class="setup-list small">
    <li>{{ __('auth.setup_2fa_step1') }}</li>
    <li>{{ __('auth.setup_2fa_step2') }}</li>
</ol>
<div class="qr-box mx-auto my-3" data-qr="{{ $uri }}"></div>
<div class="mb-3">
    <label class="form-label small text-body-secondary">{{ __('auth.manual_key') }}</label>
    <div class="input-group input-group-sm">
        <input type="text" class="form-control font-mono" value="{{ trim(chunk_split($secret, 4, ' ')) }}" readonly id="totpSecret">
        <button type="button" class="btn btn-outline-secondary" data-copy-input="#totpSecret"><i class="bi bi-clipboard"></i></button>
    </div>
</div>
<form method="POST" action="{{ route('login.2fa.setup') }}" novalidate>
    @csrf
    <div class="mb-3">
        <label for="code" class="form-label">{{ __('auth.setup_2fa_step3') }}</label>
        <input type="text" class="form-control form-control-lg text-center font-mono otp-input" id="code" name="code"
               inputmode="numeric" autocomplete="one-time-code" maxlength="6" required placeholder="000000">
    </div>
    <button class="btn btn-accent w-100 btn-lg">{{ __('auth.activate_2fa') }}</button>
</form>
@endsection
@push('scripts')
<script src="{{ asset('assets/vendor/qrcode.js') }}"></script>
@endpush
