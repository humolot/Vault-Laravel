<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="dark">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>
<body class="guest-body">
<div class="guest-bg" aria-hidden="true"></div>
<main class="guest-main">
    <div class="guest-card text-center">
        <div class="brand-mark mx-auto mb-3"><i class="bi bi-safe2-fill"></i></div>
        <div class="display-5 fw-semibold mb-2">{{ $code }}</div>
        <p class="text-body-secondary">{{ __('app.error_' . $code) }}</p>
        <a href="{{ url('/') }}" class="btn btn-accent mt-2"><i class="bi bi-house me-1"></i>{{ __('app.go_home') }}</a>
    </div>
</main>
</body>
</html>
