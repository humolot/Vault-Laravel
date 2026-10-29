@extends('layouts.app')
@section('title', __('app.vault'))
@section('content')
<div class="page-head">
    <div>
        <h1 class="page-title">{{ __('app.vault') }}</h1>
        <p class="page-sub">{{ trans_choice('app.items_count', $rows->count(), ['count' => $rows->count()]) }}</p>
    </div>
    <div class="dropdown">
        <button class="btn btn-accent dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-plus-lg me-1"></i>{{ __('app.new_item') }}</button>
        <ul class="dropdown-menu dropdown-menu-end">
            @foreach ($types as $key => $t)
                <li><a class="dropdown-item" href="{{ route('items.create', ['type' => $key]) }}"><i class="bi {{ $t['icon'] }} me-2 type-ico type-{{ $key }}"></i>{{ __('app.type_' . $key) }}</a></li>
            @endforeach
        </ul>
    </div>
</div>

@if ($rotation->isNotEmpty() || $reviews->isNotEmpty())
<div class="card suggestions mb-4">
    <div class="card-header d-flex align-items-center gap-2" role="button" data-bs-toggle="collapse" data-bs-target="#suggestBody">
        <i class="bi bi-lightbulb text-warning"></i>
        <strong>{{ __('app.suggestions') }}</strong>
        <span class="badge rounded-pill text-bg-warning">{{ $rotation->count() + $reviews->count() }}</span>
        <i class="bi bi-chevron-down ms-auto"></i>
    </div>
    <div class="collapse show" id="suggestBody">
        <div class="card-body row g-4">
            @if ($rotation->isNotEmpty())
            <div class="col-lg-6">
                <h2 class="h6 text-body-secondary"><i class="bi bi-arrow-repeat me-1"></i>{{ __('app.rotation_suggested') }}</h2>
                <ul class="list-unstyled suggest-list mb-0">
                    @foreach ($rotation->take(8) as $r)
                        <li>
                            <a href="{{ route('items.show', $r['id']) }}" class="text-truncate"><i class="bi {{ $r['icon'] }} type-ico type-{{ $r['type'] }} me-2"></i>{{ $r['name'] }}</a>
                            <span class="small text-body-secondary text-nowrap">
                                @if ($r['flagged']) <i class="bi bi-person-dash"></i> {{ __('app.access_revoked_hint') }} @else {{ __('app.days_old', ['days' => $r['age']]) }} @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
            @endif
            @if ($reviews->isNotEmpty())
            <div class="col-lg-6">
                <h2 class="h6 text-body-secondary"><i class="bi bi-person-check me-1"></i>{{ __('app.access_review') }}</h2>
                <ul class="list-unstyled suggest-list mb-0">
                    @foreach ($reviews->take(8) as $rv)
                        <li>
                            <a href="{{ route('items.show', $rv['key']->item_id) }}#shares" class="text-truncate">{{ $rv['item_name'] }}</a>
                            <span class="small text-body-secondary text-nowrap">
                                <i class="bi {{ $rv['key']->recipient_type === 'group' ? 'bi-people' : 'bi-person' }}"></i> {{ $rv['key']->recipientName() }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
            @endif
        </div>
    </div>
</div>
@endif

@if ($rows->isEmpty())
    <div class="empty-state">
        <i class="bi bi-safe2"></i>
        <h2 class="h5">{{ __('app.empty_title') }}</h2>
        <p class="text-body-secondary">{{ __('app.empty_text') }}</p>
        <a href="{{ route('items.create') }}" class="btn btn-accent"><i class="bi bi-plus-lg me-1"></i>{{ __('app.new_item') }}</a>
    </div>
@else
<div class="toolbar card mb-3">
    <div class="card-body row g-2 align-items-center">
        <div class="col-12 col-md">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" class="form-control" id="filterSearch" placeholder="{{ __('app.search_placeholder') }}" autocomplete="off">
            </div>
        </div>
        <div class="col-6 col-md-auto">
            <select class="form-select" id="filterType">
                <option value="">{{ __('app.all_types') }}</option>
                @foreach ($types as $key => $t)<option value="{{ $key }}">{{ __('app.type_' . $key) }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-auto">
            <select class="form-select" id="filterTag">
                <option value="">{{ __('app.all_labels') }}</option>
                @foreach ($tags as $tag)<option value="{{ mb_strtolower($tag) }}">{{ $tag }}</option>@endforeach
            </select>
        </div>
        <div class="col-12 col-md-auto">
            <select class="form-select" id="filterScope">
                <option value="">{{ __('app.scope_all') }}</option>
                <option value="mine">{{ __('app.scope_mine') }}</option>
                <option value="shared-with-me">{{ __('app.scope_shared_with_me') }}</option>
                <option value="shared-by-me">{{ __('app.scope_shared_by_me') }}</option>
                <option value="rotation">{{ __('app.scope_rotation') }}</option>
            </select>
        </div>
    </div>
</div>

<div class="card vault-table-card">
    <div class="table-responsive">
        <table class="table vault-table align-middle mb-0">
            <thead>
            <tr>
                <th>{{ __('app.name') }}</th>
                <th class="d-none d-xl-table-cell">{{ __('app.type') }}</th>
                <th class="d-none d-md-table-cell">{{ __('app.label') }}</th>
                <th>{{ __('app.username_email') }}</th>
                <th>{{ __('app.password') }}</th>
                <th class="text-end"></th>
            </tr>
            </thead>
            <tbody>
            @foreach ($rows as $r)
                @if ($r['locked'])
                    <tr class="row-locked" data-search="" data-type="{{ $r['type'] }}" data-tags="" data-scope="mine">
                        <td colspan="5"><i class="bi bi-lock text-danger me-2"></i><span class="text-body-secondary">{{ __('app.locked_item') }}</span></td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('items.destroy-locked', $r['id']) }}" data-confirm="{{ __('app.confirm_delete_locked') }}">@csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3"></i></button>
                            </form>
                        </td>
                    </tr>
                    @continue
                @endif
                @php $scopes = trim(($r['mine'] ? 'mine' : 'shared-with-me') . ($r['mine'] && $r['shared'] ? ' shared-by-me' : '') . ($r['rotation_due'] ? ' rotation' : '')); @endphp
                <tr data-search="{{ mb_strtolower($r['name'] . ' ' . $r['login'] . ' ' . $r['url'] . ' ' . implode(' ', $r['tags'])) }}"
                    data-type="{{ $r['type'] }}" data-tags="|{{ mb_strtolower(implode('|', $r['tags'])) }}|" data-scope="{{ $scopes }}">
                    <td>
                        <a href="{{ route('items.show', $r['id']) }}" class="item-name">
                            <span class="type-badge type-{{ $r['type'] }}"><i class="bi {{ $r['icon'] }}"></i></span>
                            <span class="min-w-0">
                                <span class="d-block fw-medium text-truncate">{{ $r['name'] }}</span>
                                <span class="d-block small text-body-secondary text-truncate">
                                    @if ($r['url']){{ parse_url($r['url'], PHP_URL_HOST) ?: $r['url'] }}@elseif(! $r['mine']){{ __('app.by', ['name' => $r['owner']]) }}@endif
                                </span>
                            </span>
                        </a>
                    </td>
                    <td class="d-none d-xl-table-cell small text-body-secondary">{{ __('app.type_' . $r['type']) }}</td>
                    <td class="d-none d-md-table-cell">
                        @foreach (array_slice($r['tags'], 0, 3) as $tag)<span class="tag">{{ $tag }}</span>@endforeach
                        @if (count($r['tags']) > 3)<span class="tag tag-more">+{{ count($r['tags']) - 3 }}</span>@endif
                    </td>
                    <td class="cell-login">
                        @if ($r['login'] !== '')
                            <span class="d-inline-flex align-items-center gap-1 mw-100">
                                <span class="text-truncate">{{ $r['login'] }}</span>
                                <button type="button" class="btn btn-icon" data-copy-text="{{ $r['login'] }}" title="{{ __('app.copy') }}"><i class="bi bi-clipboard"></i></button>
                            </span>
                        @else <span class="text-body-tertiary">—</span> @endif
                    </td>
                    <td class="cell-secret">
                        @php $field = $r['has_password'] ? 'password' : ($r['has_secret'] ? 'secret' : null); @endphp
                        @if ($field)
                            <span class="d-inline-flex align-items-center gap-1" data-secret-wrap>
                                <span class="secret-mask font-mono" data-secret-value>••••••••</span>
                                <button type="button" class="btn btn-icon" data-reveal="{{ route('items.reveal', $r['id']) }}" data-field="{{ $field }}" title="{{ __('app.show') }}"><i class="bi bi-eye"></i></button>
                                <button type="button" class="btn btn-icon" data-copy-secret="{{ route('items.reveal', $r['id']) }}" data-field="{{ $field }}" title="{{ __('app.copy') }}"><i class="bi bi-clipboard"></i></button>
                            </span>
                        @else <span class="text-body-tertiary">—</span> @endif
                    </td>
                    <td class="text-end text-nowrap">
                        @if ($r['rotation_due'])<i class="bi bi-arrow-repeat text-warning me-1" title="{{ __('app.rotation_suggested') }}"></i>@endif
                        @if ($r['shared'] || ! $r['mine'])<i class="bi bi-people text-info me-1" title="{{ $r['mine'] ? __('app.shared') : __('app.by', ['name' => $r['owner']]) }}"></i>@endif
                        @if ($r['attachments'])<i class="bi bi-paperclip text-body-secondary me-1" title="{{ $r['attachments'] }}"></i>@endif
                        <a href="{{ route('items.show', $r['id']) }}" class="btn btn-sm btn-ghost"><i class="bi bi-chevron-right"></i></a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="text-center text-body-secondary py-4 d-none" id="noResults">{{ __('app.no_results') }}</div>
</div>
@endif
@endsection
