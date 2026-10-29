@extends('layouts.app')
@section('title', __('app.groups'))
@section('content')
<div class="page-head">
    <div>
        <h1 class="page-title">{{ __('app.groups') }}</h1>
        <p class="page-sub">{{ __('app.groups_sub') }}</p>
    </div>
    @if (auth()->user()->isAdmin())
        <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#newGroup"><i class="bi bi-plus-lg me-1"></i>{{ __('app.new_group') }}</button>
    @endif
</div>

@if ($groups->isEmpty())
    <div class="empty-state"><i class="bi bi-people"></i><p class="text-body-secondary mb-0">{{ __('app.no_groups') }}</p></div>
@else
<div class="row g-3">
    @foreach ($groups as $g)
        @php $m = $memberships[$g->id] ?? null; @endphp
        <div class="col-md-6 col-xl-4">
            <a href="{{ route('groups.show', $g) }}" class="card card-pad group-card h-100" style="--group-color: {{ $g->color }}">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <span class="group-dot"><i class="bi bi-people-fill"></i></span>
                    <div class="min-w-0">
                        <div class="fw-semibold text-truncate">{{ $g->name }}</div>
                        <div class="small text-body-secondary">
                            @if ($m) {{ $m->role === 'manager' ? __('app.role_manager') : __('app.role_member') }} @if (! $m->wrapped_key)<span class="text-warning">· {{ __('app.pending_key') }}</span>@endif
                            @else {{ __('app.not_member') }} @endif
                        </div>
                    </div>
                </div>
                @if ($g->description)<p class="small text-body-secondary mb-2">{{ $g->description }}</p>@endif
                <div class="d-flex gap-3 small text-body-secondary mt-auto">
                    <span><i class="bi bi-person"></i> {{ $g->users_count }}</span>
                    <span><i class="bi bi-key"></i> {{ $itemCounts[$g->id] ?? 0 }}</span>
                </div>
            </a>
        </div>
    @endforeach
</div>
@endif

@if (auth()->user()->isAdmin())
<div class="modal fade" id="newGroup" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('groups.store') }}" class="modal-content">
            @csrf
            <div class="modal-header"><h2 class="modal-title h5">{{ __('app.new_group') }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label" for="gname">{{ __('app.name') }}</label><input class="form-control" id="gname" name="name" required maxlength="80"></div>
                <div class="mb-3"><label class="form-label" for="gdesc">{{ __('app.description') }}</label><input class="form-control" id="gdesc" name="description" maxlength="255"></div>
                <div><label class="form-label" for="gcolor">{{ __('app.color') }}</label><input type="color" class="form-control form-control-color" id="gcolor" name="color" value="#14b8a6"></div>
                <p class="small text-body-secondary mt-3 mb-0"><i class="bi bi-info-circle"></i> {{ __('app.group_creator_manager') }}</p>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-ghost" data-bs-dismiss="modal">{{ __('app.cancel') }}</button><button class="btn btn-accent">{{ __('app.create') }}</button></div>
        </form>
    </div>
</div>
@endif
@endsection
