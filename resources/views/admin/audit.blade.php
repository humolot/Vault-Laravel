@extends('layouts.app')
@section('title', __('app.audit'))
@section('content')
<div class="page-head">
    <div><h1 class="page-title">{{ __('app.audit') }}</h1><p class="page-sub">{{ __('app.audit_sub') }}</p></div>
</div>
<form method="GET" class="card card-pad mb-3 row g-2 mx-0">
    <div class="col-md-5"><select name="user_id" class="form-select"><option value="">{{ __('app.all_users') }}</option>
        @foreach ($users as $usr)<option value="{{ $usr->id }}" @selected(request('user_id') == $usr->id)>{{ $usr->name }}</option>@endforeach</select></div>
    <div class="col-md-5"><input name="action" class="form-control" value="{{ request('action') }}" placeholder="{{ __('app.action_filter') }}"></div>
    <div class="col-md-2"><button class="btn btn-outline-secondary w-100"><i class="bi bi-funnel"></i> {{ __('app.filter') }}</button></div>
</form>
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 small">
            <thead><tr><th>{{ __('app.date') }}</th><th>{{ __('app.user') }}</th><th>{{ __('app.action') }}</th><th class="d-none d-md-table-cell">{{ __('app.item') }}</th><th>IP</th><th class="d-none d-lg-table-cell">{{ __('app.details') }}</th></tr></thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="text-nowrap">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                    <td>{{ $log->user?->name ?? '—' }}</td>
                    <td><span class="badge audit-badge audit-{{ \Illuminate\Support\Str::before($log->action, '.') }}">{{ $log->action }}</span></td>
                    <td class="d-none d-md-table-cell font-mono">@if ($log->item_id){{ \Illuminate\Support\Str::limit($log->item_id, 10, '…') }}@endif</td>
                    <td class="font-mono">{{ $log->ip }}</td>
                    <td class="d-none d-lg-table-cell text-body-secondary font-mono text-truncate audit-meta">{{ $log->meta ? json_encode($log->meta, JSON_UNESCAPED_UNICODE) : '' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-body-secondary py-4">{{ __('app.no_results') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $logs->links('pagination::bootstrap-5') }}</div>
@endsection
