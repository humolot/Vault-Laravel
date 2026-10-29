@extends(!empty($inApp) ? 'layouts.app' : 'layouts.guest')
@section('title', __('auth.recovery_key'))
@section('subtitle', __('auth.recovery_key_subtitle'))
@section('content')
<div class="{{ !empty($inApp) ? 'card card-pad mx-auto narrow' : '' }}">
    @if (!empty($inApp))<h1 class="h5 mb-3"><i class="bi bi-life-preserver me-2"></i>{{ __('auth.recovery_key') }}</h1>@endif
    <div class="alert alert-danger small d-flex gap-2"><i class="bi bi-exclamation-octagon"></i><div>{{ __('auth.recovery_key_warning') }}</div></div>
    <div class="recovery-key font-mono text-center mb-3" id="rk">{{ $recoveryKey }}</div>
    <div class="d-flex gap-2 mb-4">
        <button type="button" class="btn btn-outline-secondary flex-grow-1" data-copy-text="{{ $recoveryKey }}"><i class="bi bi-clipboard me-1"></i>{{ __('app.copy') }}</button>
        <button type="button" class="btn btn-outline-secondary flex-grow-1" data-download-text="{{ config('app.name') }} — {{ __('auth.recovery_key') }}&#13;&#10;{{ $recoveryKey }}" data-filename="vault-recovery-key.txt"><i class="bi bi-download me-1"></i>{{ __('app.download') }}</button>
    </div>
    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="ack" data-enable="#continueBtn"><label class="form-check-label small" for="ack">{{ __('auth.saved_ack') }}</label></div>
    <a href="{{ !empty($inApp) ? route('profile') : route('login') }}" class="btn btn-accent w-100 btn-lg disabled" id="continueBtn" aria-disabled="true">{{ __('auth.saved_continue') }}</a>
</div>
@endsection
