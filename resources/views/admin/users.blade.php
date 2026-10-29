@extends('layouts.app')
@section('title', __('app.users'))
@section('content')
<div class="page-head">
    <div>
        <h1 class="page-title">{{ __('app.users') }}</h1>
        <p class="page-sub">{{ __('app.users_sub') }}</p>
    </div>
    <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#inviteUser"><i class="bi bi-person-plus me-1"></i>{{ __('app.invite_user') }}</button>
</div>

@if (session('invite_link'))
<div class="alert alert-info">
    <div class="fw-semibold mb-1"><i class="bi bi-link-45deg"></i> {{ __('app.invite_link_for', ['email' => session('invite_for')]) }}</div>
    <div class="input-group input-group-sm">
        <input type="text" class="form-control font-mono" id="inviteLink" value="{{ session('invite_link') }}" readonly>
        <button class="btn btn-outline-secondary" type="button" data-copy-input="#inviteLink"><i class="bi bi-clipboard"></i></button>
    </div>
    <div class="small mt-2">{{ __('app.invite_link_help', ['hours' => config('vault.invite_hours')]) }}</div>
</div>
@endif

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr>
                <th>{{ __('app.user') }}</th><th>{{ __('app.status') }}</th><th class="d-none d-md-table-cell">{{ __('app.security') }}</th>
                <th class="d-none d-lg-table-cell">{{ __('app.last_login') }}</th><th class="text-end"></th>
            </tr></thead>
            <tbody>
            @foreach ($users as $usr)
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="avatar">{{ $usr->initials() }}</span>
                            <div class="min-w-0">
                                <div class="fw-medium text-truncate">{{ $usr->name }} @if ($usr->isAdmin())<span class="badge text-bg-primary ms-1">admin</span>@endif</div>
                                <div class="small text-body-secondary text-truncate">{{ $usr->email }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        @if (! $usr->is_active)<span class="badge text-bg-secondary">{{ __('app.status_disabled') }}</span>
                        @elseif ($usr->isLocked())<span class="badge text-bg-danger">{{ __('app.status_locked') }}</span>
                        @elseif (! $usr->isActivated())<span class="badge text-bg-warning">{{ __('app.status_invited') }}</span>
                        @else<span class="badge text-bg-success">{{ __('app.status_active') }}</span>@endif
                    </td>
                    <td class="d-none d-md-table-cell small">
                        <span class="me-2 {{ $usr->hasTwoFactor() ? 'text-success' : 'text-body-tertiary' }}"><i class="bi bi-shield-check"></i> 2FA</span>
                        <span class="me-2 {{ $usr->passkeys_count ? 'text-success' : 'text-body-tertiary' }}"><i class="bi bi-fingerprint"></i> {{ $usr->passkeys_count }}</span>
                        <span class="text-body-secondary"><i class="bi bi-key"></i> {{ $usr->owned_items_count }}</span>
                    </td>
                    <td class="d-none d-lg-table-cell small text-body-secondary">{{ $usr->last_login_at?->format('d/m/Y H:i') ?? '—' }}<br>{{ $usr->last_login_ip }}</td>
                    <td class="text-end">
                        @if ((int) $usr->id !== (int) auth()->id())
                        <div class="dropdown">
                            <button class="btn btn-icon" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                @php
                                    $actions = [
                                        'toggle' => [$usr->is_active ? __('app.disable') : __('app.enable'), 'bi-toggle-on', false],
                                        'role' => [$usr->isAdmin() ? __('app.make_user') : __('app.make_admin'), 'bi-person-badge', false],
                                        'unlock' => [__('app.unlock'), 'bi-unlock', false],
                                        'reset_2fa' => [__('app.reset_2fa'), 'bi-shield-x', __('app.confirm_reset_2fa')],
                                    ];
                                    if (! $usr->isActivated()) {
                                        $actions['invite'] = [__('app.new_invite_link'), 'bi-link-45deg', false];
                                    }
                                    $actions['reset_account'] = [__('app.reset_account'), 'bi-exclamation-triangle', __('app.confirm_reset_account')];
                                @endphp
                                @foreach ($actions as $act => [$label, $icon, $confirm])
                                    <li><form method="POST" action="{{ route('admin.users.update', $usr) }}" @if($confirm) data-confirm="{{ $confirm }}" @endif>@csrf @method('PATCH')
                                        <input type="hidden" name="action" value="{{ $act }}">
                                        <button class="dropdown-item {{ $act === 'reset_account' ? 'text-danger' : '' }}"><i class="bi {{ $icon }} me-2"></i>{{ $label }}</button></form></li>
                                @endforeach
                                <li><hr class="dropdown-divider"></li>
                                <li><form method="POST" action="{{ route('admin.users.destroy', $usr) }}" data-confirm="{{ __('app.confirm_delete_user') }}">@csrf @method('DELETE')
                                    <button class="dropdown-item text-danger"><i class="bi bi-trash3 me-2"></i>{{ __('app.delete') }}</button></form></li>
                            </ul>
                        </div>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="inviteUser" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('admin.users.store') }}" class="modal-content">
            @csrf
            <div class="modal-header"><h2 class="modal-title h5">{{ __('app.invite_user') }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">{{ __('auth.name') }}</label><input class="form-control" name="name" required maxlength="120"></div>
                <div class="mb-3"><label class="form-label">{{ __('auth.email') }}</label><input type="email" class="form-control" name="email" required maxlength="190"></div>
                <div class="row g-3">
                    <div class="col-6"><label class="form-label">{{ __('app.role') }}</label>
                        <select name="role" class="form-select"><option value="user">{{ __('app.role_user') }}</option><option value="admin">{{ __('app.role_admin') }}</option></select></div>
                    <div class="col-6"><label class="form-label">{{ __('app.language') }}</label>
                        <select name="locale" class="form-select">@foreach (\App\Http\Middleware\SetLocale::SUPPORTED as $c => $l)<option value="{{ $c }}" @selected(app()->getLocale() === $c)>{{ $l }}</option>@endforeach</select></div>
                </div>
                <p class="small text-body-secondary mt-3 mb-0">{{ __('app.invite_help') }}</p>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-ghost" data-bs-dismiss="modal">{{ __('app.cancel') }}</button><button class="btn btn-accent">{{ __('app.generate_invite') }}</button></div>
        </form>
    </div>
</div>
@endsection
