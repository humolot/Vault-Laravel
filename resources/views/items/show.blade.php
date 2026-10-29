@extends('layouts.app')
@section('title', $data['name'] ?? '')
@section('content')
@php $reveal = route('items.reveal', $item); @endphp
@php $canEdit = in_array($perm, ['owner', 'edit'], true); @endphp
<div class="page-head">
    <div class="d-flex align-items-center gap-3 min-w-0">
        <span class="type-badge type-badge-lg type-{{ $item->type }}"><i class="bi {{ $type['icon'] }}"></i></span>
        <div class="min-w-0">
            <a href="{{ route('dashboard') }}" class="small link-secondary"><i class="bi bi-arrow-left"></i> {{ __('app.vault') }}</a>
            <h1 class="page-title text-truncate mb-1">{{ $data['name'] ?? '' }}</h1>
            <div class="d-flex flex-wrap gap-1 align-items-center">
                <span class="badge bg-secondary-subtle text-body border">{{ __('app.type_' . $item->type) }}</span>
                @foreach ($data['tags'] ?? [] as $tag)<span class="tag">{{ $tag }}</span>@endforeach
                <span class="badge perm-badge perm-{{ $perm }}">{{ __('app.perm_' . $perm) }}</span>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2">
        @if ($canEdit)<a href="{{ route('items.edit', $item) }}" class="btn btn-outline-secondary"><i class="bi bi-pencil me-1"></i>{{ __('app.edit') }}</a>@endif
        @if ($perm === 'owner')
            <form method="POST" action="{{ route('items.destroy', $item) }}" data-confirm="{{ __('app.confirm_trash') }}">@csrf @method('DELETE')
                <button class="btn btn-outline-danger"><i class="bi bi-trash3"></i></button>
            </form>
        @endif
    </div>
</div>

@if ($rotationDue)
<div class="alert alert-warning d-flex flex-wrap align-items-center gap-2">
    <i class="bi bi-arrow-repeat fs-5"></i>
    <div class="flex-grow-1">
        <strong>{{ __('app.rotation_suggested') }}.</strong>
        @if ($item->rotation_flagged_at) {{ __('app.rotation_reason_revoked') }} @else {{ __('app.rotation_reason_age', ['days' => $item->passwordAgeDays()]) }} @endif
    </div>
    <a href="{{ route('items.edit', $item) }}" class="btn btn-sm btn-warning">{{ __('app.change_now') }}</a>
    <form method="POST" action="{{ route('items.snooze', $item) }}">@csrf<input type="hidden" name="days" value="30">
        <button class="btn btn-sm btn-ghost">{{ __('app.snooze_30') }}</button>
    </form>
</div>
@endif

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card card-pad mb-4">
            <dl class="detail-list mb-0">
                @if (($data['username'] ?? '') !== '')
                    <dt>{{ $item->type === 'wifi' ? __('app.f_wifi_ssid') : __('app.username_email') }}</dt>
                    <dd><span class="font-mono text-break">{{ $data['username'] }}</span>
                        <button class="btn btn-icon" data-copy-text="{{ $data['username'] }}" title="{{ __('app.copy') }}"><i class="bi bi-clipboard"></i></button></dd>
                @endif
                @if (($data['password'] ?? '') !== '')
                    <dt>{{ __('app.password') }}</dt>
                    <dd data-secret-wrap>
                        <span class="font-mono secret-mask text-break" data-secret-value>••••••••••••</span>
                        <button class="btn btn-icon" data-reveal="{{ $reveal }}" data-field="password" title="{{ __('app.show') }}"><i class="bi bi-eye"></i></button>
                        <button class="btn btn-icon" data-copy-secret="{{ $reveal }}" data-field="password" title="{{ __('app.copy') }}"><i class="bi bi-clipboard"></i></button>
                    </dd>
                @endif
                @if (($data['host'] ?? '') !== '')
                    <dt>{{ __('app.f_host') }}</dt>
                    <dd><span class="font-mono">{{ $data['host'] }}{{ ($data['port'] ?? '') !== '' ? ':' . $data['port'] : '' }}</span>
                        <button class="btn btn-icon" data-copy-text="{{ $data['host'] }}"><i class="bi bi-clipboard"></i></button></dd>
                @endif
                @if (($data['phone'] ?? '') !== '')
                    <dt>{{ __('app.f_phone') }}</dt>
                    <dd><span class="font-mono">{{ $data['phone'] }}</span>
                        <button class="btn btn-icon" data-copy-text="{{ $data['phone'] }}"><i class="bi bi-clipboard"></i></button>
                        @if (($data['carrier'] ?? '') !== '')<span class="badge bg-secondary-subtle text-body border ms-1">{{ $data['carrier'] }}</span>@endif
                    </dd>
                @endif
                @if (($data['used_for'] ?? '') !== '')
                    <dt>{{ __('app.f_used_for') }}</dt>
                    <dd class="pre-wrap">{{ $data['used_for'] }}</dd>
                @endif
                @if (($data['url'] ?? '') !== '')
                    <dt>{{ __('app.f_url') }}</dt>
                    <dd class="text-break">
                        @if (preg_match('#^https?://#i', $data['url']))
                            <a href="{{ $data['url'] }}" target="_blank" rel="noopener noreferrer">{{ $data['url'] }} <i class="bi bi-box-arrow-up-right small"></i></a>
                        @else {{ $data['url'] }} @endif
                    </dd>
                @endif
                @if (($data['secret'] ?? '') !== '')
                    @php
                        $secretLabel = match ($item->type) {
                            'recovery_codes' => __('app.f_recovery_codes'),
                            'seed_phrase' => __('app.f_seed_phrase'),
                            'api_key' => __('app.f_api_key'),
                            'note' => __('app.f_note_content'),
                            default => __('app.f_secret'),
                        };
                    @endphp
                    <dt>{{ $secretLabel }}</dt>
                    <dd data-secret-wrap class="d-block">
                        <pre class="secret-block font-mono mb-2" data-secret-value>••••••••••••••••</pre>
                        <button class="btn btn-sm btn-outline-secondary" data-reveal="{{ $reveal }}" data-field="secret"><i class="bi bi-eye me-1"></i>{{ __('app.show') }}</button>
                        <button class="btn btn-sm btn-outline-secondary" data-copy-secret="{{ $reveal }}" data-field="secret"><i class="bi bi-clipboard me-1"></i>{{ __('app.copy') }}</button>
                    </dd>
                @endif
                @if (($data['notes'] ?? '') !== '')
                    <dt>{{ __('app.notes') }}</dt>
                    <dd class="pre-wrap">{{ $data['notes'] }}</dd>
                @endif
            </dl>
        </div>

        <div class="card card-pad mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 mb-0"><i class="bi bi-paperclip me-1"></i>{{ __('app.attachments') }}</h2>
                <span class="small text-body-secondary">{{ __('app.attachments_hint', ['mb' => round(config('vault.attachment_max_kb') / 1024)]) }}</span>
            </div>
            @if ($attachments->isEmpty())
                <p class="small text-body-secondary mb-3">{{ __('app.no_attachments') }}</p>
            @else
                <ul class="list-group list-group-flush mb-3 attach-list">
                    @foreach ($attachments as $a)
                        @php $isPdf = $a['meta']['mime'] === 'application/pdf'; @endphp
                        <li class="list-group-item d-flex align-items-center gap-3 px-0">
                            <i class="bi {{ $isPdf ? 'bi-file-earmark-pdf text-danger' : (str_starts_with($a['meta']['mime'], 'image/') ? 'bi-file-earmark-image text-info' : 'bi-file-earmark-lock') }} fs-4"></i>
                            <div class="flex-grow-1 min-w-0">
                                <div class="text-truncate">{{ $a['meta']['name'] }}</div>
                                <div class="small text-body-secondary">{{ number_format($a['model']->size / 1024, 1) }} KB · {{ $a['model']->created_at->format('d/m/Y H:i') }} · {{ $a['model']->uploader?->name }}</div>
                            </div>
                            @if ($isPdf || str_starts_with($a['meta']['mime'], 'image/') || $a['meta']['mime'] === 'text/plain')
                                <a class="btn btn-icon" href="{{ route('attachments.download', [$a['model'], 'inline' => 1]) }}" target="_blank" rel="noopener" title="{{ __('app.open') }}"><i class="bi bi-eye"></i></a>
                            @endif
                            <a class="btn btn-icon" href="{{ route('attachments.download', $a['model']) }}" title="{{ __('app.download') }}"><i class="bi bi-download"></i></a>
                            @if ($canEdit)
                                <form method="POST" action="{{ route('attachments.destroy', $a['model']) }}" data-confirm="{{ __('app.confirm_delete') }}">@csrf @method('DELETE')
                                    <button class="btn btn-icon text-danger"><i class="bi bi-trash3"></i></button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($canEdit)
                <form method="POST" action="{{ route('attachments.store', $item) }}" enctype="multipart/form-data" class="dropzone-form">
                    @csrf
                    <label class="dropzone" for="files">
                        <i class="bi bi-cloud-arrow-up fs-3"></i>
                        <span>{{ __('app.drop_files') }}</span>
                        <input type="file" name="files[]" id="files" multiple class="visually-hidden" data-auto-submit>
                    </label>
                </form>
            @endif
        </div>

        @if (! empty($history))
        <div class="card card-pad mb-4">
            <h2 class="h6 mb-3"><i class="bi bi-clock-history me-1"></i>{{ __('app.history') }}</h2>
            <ul class="list-unstyled history-list mb-0">
                @foreach ($history as $i => $h)
                    <li data-secret-wrap>
                        <div class="small text-body-secondary">{{ \Carbon\Carbon::parse($h['changed_at'])->format('d/m/Y H:i') }} · {{ $h['by'] ?? '' }} · {{ $h['field'] === 'secret' ? __('app.f_secret') : __('app.password') }}</div>
                        <span class="font-mono secret-mask" data-secret-value>••••••••</span>
                        <button class="btn btn-icon" data-reveal="{{ $reveal }}" data-field="history" data-index="{{ $i }}"><i class="bi bi-eye"></i></button>
                        <button class="btn btn-icon" data-copy-secret="{{ $reveal }}" data-field="history" data-index="{{ $i }}"><i class="bi bi-clipboard"></i></button>
                    </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>

    <div class="col-lg-5">
        <div class="card card-pad mb-4">
            <h2 class="h6 mb-3"><i class="bi bi-info-circle me-1"></i>{{ __('app.details') }}</h2>
            <dl class="meta-list mb-0">
                <dt>{{ __('app.owner') }}</dt><dd>{{ $item->owner?->name }}</dd>
                <dt>{{ __('app.created') }}</dt><dd>{{ $item->created_at->format('d/m/Y H:i') }}</dd>
                <dt>{{ __('app.updated') }}</dt><dd>{{ $item->updated_at->format('d/m/Y H:i') }}</dd>
                @if ($item->isRotatable())
                    <dt>{{ __('app.password_age') }}</dt><dd>{{ __('app.days_old', ['days' => $item->passwordAgeDays()]) }}</dd>
                    <dt>{{ __('app.rotation_every') }}</dt><dd>{{ $item->rotation_days ?: auth()->user()->rotation_days }} {{ __('app.days') }}</dd>
                @endif
            </dl>
        </div>

        @if ($perm === 'owner')
        <div class="card card-pad mb-4" id="shares">
            <h2 class="h6 mb-3"><i class="bi bi-share me-1"></i>{{ __('app.sharing') }}</h2>
            <form method="POST" action="{{ route('items.share', $item) }}" class="row g-2 mb-3">
                @csrf
                <div class="col-12">
                    <select name="target" class="form-select" required>
                        <option value="">{{ __('app.share_with') }}</option>
                        @if ($groups->isNotEmpty())
                            <optgroup label="{{ __('app.groups') }}">
                                @foreach ($groups as $g)<option value="group:{{ $g->id }}">{{ $g->name }}</option>@endforeach
                            </optgroup>
                        @endif
                        @if ($users->isNotEmpty())
                            <optgroup label="{{ __('app.users') }}">
                                @foreach ($users as $usr)<option value="user:{{ $usr->id }}">{{ $usr->name }} ({{ $usr->email }})</option>@endforeach
                            </optgroup>
                        @endif
                    </select>
                </div>
                <div class="col-7">
                    <select name="permission" class="form-select">
                        <option value="view">{{ __('app.perm_view') }}</option>
                        <option value="edit">{{ __('app.perm_edit') }}</option>
                    </select>
                </div>
                <div class="col-5"><button class="btn btn-accent w-100"><i class="bi bi-person-plus me-1"></i>{{ __('app.share') }}</button></div>
            </form>

            <ul class="list-group list-group-flush share-list">
                @foreach ($shares as $s)
                    @php $stale = $s->permission !== 'owner' && (! $s->reviewed_at || $s->reviewed_at->lt(now()->subDays(auth()->user()->access_review_days))); @endphp
                    <li class="list-group-item px-0 d-flex align-items-center gap-2">
                        <span class="avatar avatar-sm {{ $s->recipient_type === 'group' ? 'avatar-group' : '' }}" @if($s->recipient_type === 'group' && $s->recipientGroup) style="--avatar-color: {{ $s->recipientGroup->color }}" @endif>
                            <i class="bi {{ $s->recipient_type === 'group' ? 'bi-people-fill' : 'bi-person-fill' }}"></i>
                        </span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="text-truncate">{{ $s->recipientName() }}
                                @if (! $s->wrapped_key)<span class="badge text-bg-warning ms-1">{{ __('app.pending_key') }}</span>@endif
                            </div>
                            <div class="small text-body-secondary">
                                {{ __('app.perm_' . $s->permission) }}
                                @if ($s->permission !== 'owner') · {{ __('app.since', ['date' => $s->created_at->format('d/m/Y')]) }} @endif
                                @if ($stale)<span class="text-warning"> · {{ __('app.review_due') }}</span>@endif
                            </div>
                        </div>
                        @if ($s->permission !== 'owner')
                            <div class="dropdown">
                                <button class="btn btn-icon" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><form method="POST" action="{{ route('items.share.update', [$item, $s]) }}">@csrf @method('PATCH')<input type="hidden" name="permission" value="{{ $s->permission === 'view' ? 'edit' : 'view' }}">
                                        <button class="dropdown-item"><i class="bi bi-arrow-left-right me-2"></i>{{ $s->permission === 'view' ? __('app.make_edit') : __('app.make_view') }}</button></form></li>
                                    <li><form method="POST" action="{{ route('items.share.update', [$item, $s]) }}">@csrf @method('PATCH')
                                        <button class="dropdown-item"><i class="bi bi-check2-circle me-2"></i>{{ __('app.keep_access') }}</button></form></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><form method="POST" action="{{ route('items.share.revoke', [$item, $s]) }}" data-confirm="{{ __('app.confirm_revoke') }}">@csrf @method('DELETE')
                                        <button class="dropdown-item text-danger"><i class="bi bi-person-dash me-2"></i>{{ __('app.revoke') }}</button></form></li>
                                </ul>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
</div>
@endsection
