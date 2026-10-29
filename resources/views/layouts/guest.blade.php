<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="dark">
<head>
    @include('layouts.head')
</head>
<body class="guest-body">
<div class="guest-bg" aria-hidden="true"></div>
<div class="guest-top d-flex justify-content-end gap-2 p-3">
    <button type="button" class="btn btn-sm btn-ghost" data-theme-toggle title="{{ __('app.toggle_theme') }}">
        <i class="bi bi-circle-half"></i>
    </button>
    @include('partials.locale-menu')
</div>
<main class="guest-main">
    <div class="guest-card @yield('card-class')">
        <div class="text-center mb-4">
            <div class="brand-mark mx-auto mb-3"><i class="bi bi-safe2-fill"></i></div>
            <h1 class="h4 fw-semibold mb-1">{{ config('app.name') }}</h1>
            @hasSection('subtitle')
                <p class="text-body-secondary small mb-0">@yield('subtitle')</p>
            @endif
        </div>
        @hasSection('steps')
            <div class="login-steps mb-4">@yield('steps')</div>
        @endif
        @include('partials.flash')
        @yield('content')
    </div>
    <p class="text-center text-body-secondary small mt-4 mb-0">
        <i class="bi bi-lock-fill"></i> {{ __('app.footer_security') }}
    </p>
</main>
@include('layouts.scripts')
</body>
</html>
