@extends('layouts.app')
@section('title', $group->name)
@section('content')
<div class="page-head">
    <div class="d-flex align-items-center gap-3">
        <span class="group-dot group-dot-lg" style="--group-color: {{ $group->color }}"><i class="bi bi-people-fill"></i></span>
        <div>
            <a href="{{ route('groups.index') }}" class="small link-secondary"><i class="bi bi-arrow-left"></i> {{ __('app.groups') }}</a>
            <h1 class="page-title mb-0">{{ $group->name }}</h1>
            @if ($group->description)<p class="page-sub mb-0">{{ $group->description }}</p>@endif
        </div>
    </div>
    <div class="d-flex gap-2">
        @if ($canManage || auth()->user()->isAdmin())
            <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editGroup"><i class="bi bi-pencil"></i></button>
        @endif
        @if (auth()->user()->isAdmin())
            <form method="POST" action="{{ route('groups.destroy', $group) }}" data-confirm="{{ __('app.confirm_delete_group') }}">@csrf @method('DELETE')
                <button class="btn btn-outline-danger"><i class="bi bi-trash3"></i></button>
            </form>
        @endif
    </div>
</div>

@if (! $membership)
    <div class="alert alert-info small">{{ __('app.admin_not_member') }}</div>
@endif

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <strong>{{ __('app.members') }}</strong>
                <span class="small text-body-secondary">{{ trans_choice('app.items_count', $itemCount, ['count' => $itemCount]) }}</span>
            </div>
            <ul class="list-group list-group-flush">
                @foreach ($members as $m)
                    <li class="list-group-item d-flex align-items-center gap-3">
                        <span class="avatar">{{ $m->initials() }}</span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="text-truncate">{{ $m->name }}</div>
                            <div class="small text-body-secondary text-truncate">{{ $m->email }}</div>
                        </div>
                        @if (! $m->pivot->wrapped_key)<span class="badge text-bg-warning" title="{{ __('app.pending_key_help') }}">{{ __('app.pending_key') }}</span>@endif
                        <span class="badge {{ $m->pivot->role === 'manager' ? 'text-bg-primary' : 'text-bg-secondary' }}">{{ $m->pivot->role === 'manager' ? __('app.role_manager') : __('app.role_member') }}</span>
                        @if ($canManage)
                            <div class="dropdown">
                                <button class="btn btn-icon" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><form method="POST" action="{{ route('groups.members.update', [$group, $m]) }}">@csrf @method('PATCH')
                                        <input type="hidden" name="role" value="{{ $m->pivot->role === 'manager' ? 'member' : 'manager' }}">
                                        <button class="dropdown-item">{{ $m->pivot->role === 'manager' ? __('app.make_member') : __('app.make_manager') }}</button></form></li>
                                    <li><form method="POST" action="{{ route('groups.members.remove', [$group, $m]) }}" data-confirm="{{ __('app.confirm_remove_member') }}">@csrf @method('DELETE')
                                        <button class="dropdown-item text-danger">{{ __('app.remove') }}</button></form></li>
                                </ul>
                            </div>
                        @elseif ((int) $m->id === (int) auth()->id())
                            <form method="POST" action="{{ route('groups.members.remove', [$group, $m]) }}" data-confirm="{{ __('app.confirm_leave') }}">@csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">{{ __('app.leave') }}</button></form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
    <div class="col-lg-4">
        @if ($canManage)
        <div class="card card-pad">
            <h2 class="h6 mb-3"><i class="bi bi-person-plus me-1"></i>{{ __('app.add_member') }}</h2>
            <form method="POST" action="{{ route('groups.members.add', $group) }}">
                @csrf
                <select name="user_id" class="form-select mb-2" required>
                    <option value="">{{ __('app.select_user') }}</option>
                    @foreach ($candidates as $c)
                        <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->email }}){{ $c->public_key ? '' : ' — ' . __('app.not_activated') }}</option>
                    @endforeach
                </select>
                <select name="role" class="form-select mb-3">
                    <option value="member">{{ __('app.role_member') }}</option>
                    <option value="manager">{{ __('app.role_manager') }}</option>
                </select>
                <button class="btn btn-accent w-100">{{ __('app.add') }}</button>
            </form>
            <p class="small text-body-secondary mt-3 mb-0">{{ __('app.group_key_help') }}</p>
        </div>
        @endif
    </div>
</div>

@if ($canManage || auth()->user()->isAdmin())
<div class="modal fade" id="editGroup" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('groups.update', $group) }}" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header"><h2 class="modal-title h5">{{ __('app.edit') }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">{{ __('app.name') }}</label><input class="form-control" name="name" value="{{ $group->name }}" required maxlength="80"></div>
                <div class="mb-3"><label class="form-label">{{ __('app.description') }}</label><input class="form-control" name="description" value="{{ $group->description }}" maxlength="255"></div>
                <div><label class="form-label">{{ __('app.color') }}</label><input type="color" class="form-control form-control-color" name="color" value="{{ $group->color }}"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-ghost" data-bs-dismiss="modal">{{ __('app.cancel') }}</button><button class="btn btn-accent">{{ __('app.save') }}</button></div>
        </form>
    </div>
</div>
@endif
@endsection
