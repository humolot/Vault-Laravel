@extends(!empty($inApp) ? 'layouts.app' : 'layouts.guest')
@section('title', __('auth.recovery_codes'))
@section('subtitle', __('auth.recovery_codes_subtitle'))
@section('content')
<div class="{{ !empty($inApp) ? 'card card-pad mx-auto narrow' : '' }}">
    @if (!empty($inApp))<h1 class="h5 mb-3"><i class="bi bi-key me-2"></i>{{ __('auth.recovery_codes') }}</h1>@endif
    <div class="alert alert-warning small d-flex gap-2"><i class="bi bi-exclamation-triangle"></i><div>{{ __('auth.recovery_codes_warning') }}</div></div>
    <div class="codes-grid font-mono mb-3" id="codesBlock">
        @foreach ($codes as $code)<span>{{ $code }}</span>@endforeach
    </div>
    <div class="d-flex gap-2 mb-4">
        <button type="button" class="btn btn-outline-secondary flex-grow-1" data-copy-text="{{ implode("\n", $codes) }}"><i class="bi bi-clipboard me-1"></i>{{ __('app.copy') }}</button>
        <button type="button" class="btn btn-outline-secondary flex-grow-1" data-download-text="{{ implode("\r\n", $codes) }}" data-filename="2fa-recovery-codes.txt"><i class="bi bi-download me-1"></i>{{ __('app.download') }}</button>
    </div>
    @if (!empty($inApp))
        <a href="{{ $continue }}" class="btn btn-accent w-100">{{ __('auth.saved_continue') }}</a>
    @else
        <form method="POST" action="{{ $continue }}">@csrf
            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="ack" required data-enable="#continueBtn"><label class="form-check-label small" for="ack">{{ __('auth.saved_ack') }}</label></div>
            <button class="btn btn-accent w-100 btn-lg" id="continueBtn" disabled>{{ __('auth.saved_continue') }}</button>
        </form>
    @endif
</div>
@endsection
