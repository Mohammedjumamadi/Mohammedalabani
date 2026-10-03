@extends('layouts.panel')

@section('panel', 'admin')
@section('title', __('nav.admin.activity'))

@section('content')
    <div class="page-head"><h1>{{ __('nav.admin.activity') }}</h1></div>

    <form method="GET" class="aimv-card card-body-pad mb-4" role="search">
        <div class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label small mb-1" for="action">{{ __('admin.activity.action') }}</label>
                <select id="action" name="action" class="form-select"><option value="">{{ __('common.all') }}</option>
                    @foreach ($actions as $a)<option value="{{ $a }}" @selected(($filters['action'] ?? '') === $a)>{{ $a }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label small mb-1" for="user">{{ __('admin.activity.user') }}</label><input id="user" name="user" class="form-control" value="{{ $filters['user'] ?? '' }}" placeholder="email"></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-1" for="from">{{ __('admin.activity.from') }}</label><input id="from" name="from" type="date" class="form-control" value="{{ $filters['from'] ?? '' }}"></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-1" for="to">{{ __('admin.activity.to') }}</label><input id="to" name="to" type="date" class="form-control" value="{{ $filters['to'] ?? '' }}"></div>
            <div class="col-12 col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-funnel" aria-hidden="true"></i> {{ __('common.filter') }}</button></div>
        </div>
    </form>

    <div class="aimv-card">
        <div class="table-responsive"><table class="table table-aimv table-hover align-middle mb-0"><caption class="visually-hidden">{{ __('nav.admin.activity') }}</caption>
            <thead><tr><th scope="col">{{ __('admin.activity.when') }}</th><th scope="col">{{ __('admin.activity.action') }}</th><th scope="col">{{ __('admin.activity.description') }}</th><th scope="col">{{ __('admin.activity.user') }}</th><th scope="col">IP</th></tr></thead>
            <tbody>@forelse ($logs as $log)
                <tr><td class="small text-nowrap">{{ $log->created_at->translatedFormat('j M Y H:i:s') }}</td><td class="ltr-text small">{{ $log->action }}</td><td class="small">{{ $log->description }}</td>
                    <td class="small ltr-text">{{ $log->user?->email ?? '—' }}</td><td class="small ltr-text">{{ $log->ip_address ?? '—' }}</td></tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-5">{{ __('common.no_data') }}</td></tr>
            @endforelse</tbody></table></div>
        @if ($logs->hasPages())<div class="card-body-pad border-top">{{ $logs->links() }}</div>@endif
    </div>
@endsection
