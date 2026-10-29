@extends('layouts.app')
@section('title', __('app.trash'))
@section('content')
<div class="page-head">
    <div>
        <h1 class="page-title">{{ __('app.trash') }}</h1>
        <p class="page-sub">{{ __('app.trash_sub') }}</p>
    </div>
</div>
@if ($entries->isEmpty())
    <div class="empty-state"><i class="bi bi-trash3"></i><p class="text-body-secondary mb-0">{{ __('app.trash_empty') }}</p></div>
@else
<div class="card">
    <ul class="list-group list-group-flush">
        @foreach ($entries as $e)
            <li class="list-group-item d-flex align-items-center gap-3 py-3">
                <span class="type-badge type-{{ $e['item']->type }}"><i class="bi {{ $types[$e['item']->type]['icon'] ?? 'bi-box-seam' }}"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-medium text-truncate">{{ $e['data']['name'] ?? __('app.locked_item') }}</div>
                    <div class="small text-body-secondary">{{ __('app.deleted_at', ['date' => $e['item']->deleted_at->format('d/m/Y H:i')]) }}</div>
                </div>
                <form method="POST" action="{{ route('trash.restore', $e['item']->id) }}">@csrf
                    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i>{{ __('app.restore') }}</button>
                </form>
                <form method="POST" action="{{ route('trash.purge', $e['item']->id) }}" data-confirm="{{ __('app.confirm_purge') }}">@csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-octagon me-1"></i>{{ __('app.delete_forever') }}</button>
                </form>
            </li>
        @endforeach
    </ul>
</div>
@endif
@endsection
