<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="dark">
<head>
    @include('layouts.head')
</head>
<body class="app-body">
@php $u = auth()->user(); @endphp
@php $route = request()->route()?->getName() ?? ''; @endphp

<aside class="app-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar">
    <div class="sidebar-inner">
        <a href="{{ route('dashboard') }}" class="sidebar-brand">
            <span class="brand-mark brand-mark-sm"><i class="bi bi-safe2-fill"></i></span>
            <span class="fw-semibold">{{ config('app.name') }}</span>
        </a>

        <a href="{{ route('items.create') }}" class="btn btn-accent w-100 mb-3">
            <i class="bi bi-plus-lg me-1"></i> {{ __('app.new_item') }}
        </a>

        <nav class="sidebar-nav">
            <div class="nav-label">{{ __('app.vault') }}</div>
            <a href="{{ route('dashboard') }}" class="{{ $route === 'dashboard' || str_starts_with($route, 'items.') ? 'active' : '' }}">
                <i class="bi bi-grid-1x2"></i> {{ __('app.all_items') }}
            </a>
            <a href="{{ route('groups.index') }}" class="{{ str_starts_with($route, 'groups.') ? 'active' : '' }}">
                <i class="bi bi-people"></i> {{ __('app.groups') }}
            </a>
            <a href="{{ route('trash') }}" class="{{ $route === 'trash' ? 'active' : '' }}">
                <i class="bi bi-trash3"></i> {{ __('app.trash') }}
            </a>

            @if ($u->isAdmin())
                <div class="nav-label">{{ __('app.administration') }}</div>
                <a href="{{ route('admin.users') }}" class="{{ $route === 'admin.users' ? 'active' : '' }}">
                    <i class="bi bi-person-gear"></i> {{ __('app.users') }}
                </a>
                <a href="{{ route('admin.audit') }}" class="{{ $route === 'admin.audit' ? 'active' : '' }}">
                    <i class="bi bi-journal-text"></i> {{ __('app.audit') }}
                </a>
            @endif
        </nav>

        <div class="sidebar-footer">
            <div class="d-flex align-items-center gap-2 mb-3">
                <button type="button" class="btn btn-sm btn-ghost flex-grow-1" data-theme-toggle>
                    <i class="bi bi-circle-half me-1"></i> {{ __('app.theme') }}
                </button>
                @include('partials.locale-menu', ['class' => 'dropup'])
            </div>
            <a href="{{ route('profile') }}" class="sidebar-user {{ $route === 'profile' ? 'active' : '' }}">
                <span class="avatar">{{ $u->initials() }}</span>
                <span class="flex-grow-1 text-truncate">
                    <span class="d-block fw-medium text-truncate">{{ $u->name }}</span>
                    <span class="d-block small text-body-secondary text-truncate">{{ $u->email }}</span>
                </span>
                <i class="bi bi-gear"></i>
            </a>
            <form method="POST" action="{{ route('logout') }}" class="mt-2">
                @csrf
                <button class="btn btn-sm btn-outline-danger w-100"><i class="bi bi-lock-fill me-1"></i> {{ __('app.lock_and_exit') }}</button>
            </form>
        </div>
    </div>
</aside>

<div class="app-main">
    <header class="app-topbar d-lg-none">
        <button class="btn btn-ghost" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar">
            <i class="bi bi-list fs-4"></i>
        </button>
        <span class="fw-semibold"><i class="bi bi-safe2-fill text-accent me-1"></i>{{ config('app.name') }}</span>
        <a href="{{ route('items.create') }}" class="btn btn-ghost"><i class="bi bi-plus-lg fs-5"></i></a>
    </header>

    <main class="app-content">
        @include('partials.flash')
        @yield('content')
    </main>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toasts"></div>

<div class="modal fade" id="idleModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <i class="bi bi-hourglass-split fs-1 text-warning"></i>
                <p class="mt-3 mb-3">{{ __('app.idle_warning') }}</p>
                <button type="button" class="btn btn-accent w-100" data-idle-stay>{{ __('app.stay_signed_in') }}</button>
            </div>
        </div>
    </div>
</div>

@include('layouts.scripts')
</body>
</html>
