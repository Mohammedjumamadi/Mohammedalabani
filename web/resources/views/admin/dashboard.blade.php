@extends('layouts.panel')

@section('panel', 'admin')
@section('title', __('nav.admin.dashboard'))

@section('content')
    <div class="page-head">
        <h1>{{ __('nav.admin.dashboard') }}</h1>
        <a href="{{ route('admin.statistics') }}" class="btn btn-outline-primary"><i class="bi bi-graph-up" aria-hidden="true"></i> {{ __('nav.admin.statistics') }}</a>
    </div>

    <h2 class="h6 text-muted mb-2">{{ __('admin.dashboard.users') }}</h2>
    <div class="row g-3 mb-4">
        @foreach ([['total', 'bi-people', '', 'admin.dashboard.users_total'], ['active', 'bi-person-check', 'is-real', 'admin.dashboard.users_active'], ['suspended', 'bi-person-slash', 'is-fake', 'admin.dashboard.users_suspended']] as [$k, $icon, $tone, $label])
            <div class="col-6 col-lg-4">
                <div class="aimv-card stat-card {{ $tone }}"><div class="stat-icon"><i class="bi {{ $icon }}" aria-hidden="true"></i></div>
                    <div><div class="stat-value">{{ number_format($users[$k]) }}</div><div class="stat-label">{{ __($label) }}</div></div></div>
            </div>
        @endforeach
    </div>

    <h2 class="h6 text-muted mb-2">{{ __('admin.dashboard.verifications') }}</h2>
    <div class="row g-3 mb-4">
        @foreach ([['total', 'bi-collection', '', 'admin.dashboard.v_total'], ['completed', 'bi-check2-all', '', 'admin.dashboard.v_completed'], ['failed', 'bi-slash-circle', 'is-fake', 'admin.dashboard.v_failed'], ['real', 'bi-check-circle-fill', 'is-real', 'admin.dashboard.v_real'], ['fake', 'bi-x-octagon-fill', 'is-fake', 'admin.dashboard.v_fake'], ['uncertain', 'bi-exclamation-triangle-fill', 'is-uncertain', 'admin.dashboard.v_uncertain']] as [$k, $icon, $tone, $label])
            <div class="col-6 col-lg-4 col-xl-2">
                <div class="aimv-card stat-card {{ $tone }}"><div class="stat-icon"><i class="bi {{ $icon }}" aria-hidden="true"></i></div>
                    <div><div class="stat-value">{{ number_format($verifications[$k]) }}</div><div class="stat-label">{{ __($label) }}</div></div></div>
            </div>
        @endforeach
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <section class="aimv-card h-100" aria-labelledby="ai-title">
                <div class="card-head"><h2 id="ai-title">{{ __('admin.dashboard.ai_status') }}</h2>
                    <span class="verdict-badge {{ $ai['online'] ? 'verdict-real' : 'verdict-fake' }}">
                        <i class="bi {{ $ai['online'] ? 'bi-check-circle-fill' : 'bi-x-octagon-fill' }}" aria-hidden="true"></i>
                        {{ $ai['online'] ? __('admin.dashboard.online') : __('admin.dashboard.offline') }}
                    </span>
                </div>
                <div class="card-body-pad small">
                    @if ($ai['online'])
                        <div>{{ __('admin.dashboard.ai_version') }}: <span class="ltr-text">{{ $ai['data']['version'] ?? '—' }}</span> · <span class="ltr-text">{{ $ai['data']['device'] ?? '' }}</span></div>
                        <div>FFmpeg: {{ ($ai['data']['ffmpeg'] ?? false) ? __('common.yes') : __('common.no') }}</div>
                        <ul class="mb-0 mt-2 ps-3">
                            @foreach ($ai['data']['models'] ?? [] as $m)
                                <li><span class="ltr-text">{{ $m['key'] }}</span>: {{ $m['loaded'] ? __('admin.models.loaded') : __('admin.models.not_loaded') }}@if ($m['error']) — <span class="text-danger">{{ __('admin.models.error') }}</span>@endif</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mb-0 text-muted">{{ __('admin.dashboard.ai_offline_help') }}</p>
                    @endif
                </div>
            </section>
        </div>
        <div class="col-lg-4">
            <div class="aimv-card stat-card mb-3"><div class="stat-icon"><i class="bi bi-hdd" aria-hidden="true"></i></div>
                <div><div class="stat-value ltr-text">{{ number_format($storage / 1048576, 1) }} MB</div><div class="stat-label">{{ __('admin.dashboard.storage') }}</div></div></div>
            <div class="aimv-card stat-card"><div class="stat-icon"><i class="bi bi-stopwatch" aria-hidden="true"></i></div>
                <div><div class="stat-value">{{ $avgProcessing !== null ? __('verifications.show.seconds', ['value' => number_format($avgProcessing, 1)]) : '—' }}</div><div class="stat-label">{{ __('admin.dashboard.avg_time') }}</div></div></div>
        </div>
        <div class="col-lg-4">
            <section class="aimv-card h-100" aria-labelledby="mm-title">
                <div class="card-head"><h2 id="mm-title">{{ __('admin.dashboard.top_models') }}</h2></div>
                @forelse ($models as $m)
                    <div class="card-body-pad py-2 d-flex justify-content-between {{ $loop->last ? '' : 'border-bottom' }}">
                        <span class="ltr-text small text-break">{{ $m->model_name }}</span><strong>{{ $m->total }}</strong>
                    </div>
                @empty
                    <div class="card-body-pad text-muted small">{{ __('admin.dashboard.no_models') }}</div>
                @endforelse
            </section>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-7"><section class="aimv-card h-100" aria-labelledby="d-title">
            <div class="card-head"><h2 id="d-title">{{ __('admin.dashboard.daily') }}</h2></div>
            <div class="card-body-pad"><div style="height:250px"><canvas id="dailyChart" role="img" aria-label="{{ __('admin.dashboard.daily') }}"></canvas></div></div>
        </section></div>
        <div class="col-lg-5"><section class="aimv-card h-100" aria-labelledby="t-title">
            <div class="card-head"><h2 id="t-title">{{ __('admin.dashboard.by_type') }}</h2></div>
            <div class="card-body-pad"><div style="height:250px"><canvas id="typeChart" role="img" aria-label="{{ __('admin.dashboard.by_type') }}"></canvas></div></div>
        </section></div>
        <div class="col-12"><section class="aimv-card" aria-labelledby="m-title">
            <div class="card-head"><h2 id="m-title">{{ __('admin.dashboard.monthly') }}</h2></div>
            <div class="card-body-pad"><div style="height:250px"><canvas id="monthlyChart" role="img" aria-label="{{ __('admin.dashboard.monthly') }}"></canvas></div></div>
        </section></div>
    </div>

    <section class="aimv-card" aria-labelledby="a-title">
        <div class="card-head"><h2 id="a-title">{{ __('admin.dashboard.activity') }}</h2><a href="{{ route('admin.activity.index') }}" class="small">{{ __('dashboard.view_all') }}</a></div>
        <div class="table-responsive">
            <table class="table table-aimv mb-0"><caption class="visually-hidden">{{ __('admin.dashboard.activity') }}</caption>
                <thead><tr><th scope="col">{{ __('admin.activity.action') }}</th><th scope="col">{{ __('admin.activity.description') }}</th><th scope="col">{{ __('admin.activity.user') }}</th><th scope="col">{{ __('admin.activity.when') }}</th></tr></thead>
                <tbody>
                    @forelse ($activities as $log)
                        <tr><td class="ltr-text small">{{ $log->action }}</td><td class="small">{{ $log->description }}</td><td class="small">{{ $log->user?->email ?? '—' }}</td><td class="small text-nowrap">{{ $log->created_at->diffForHumans() }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">{{ __('common.no_data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    Chart.defaults.font = { family: 'Cairo' };
    const bar = (id, labels, data, label, color) => { const el = document.getElementById(id); if (el) new Chart(el, { type: 'bar', data: { labels, datasets: [{ label, data, backgroundColor: color, borderRadius: 5 }] }, options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } } }); };
    bar('dailyChart', @js($daily['labels']), @js($daily['counts']), @js(__('admin.dashboard.daily')), '#0e7490');
    bar('monthlyChart', @js($monthly['labels']), @js($monthly['counts']), @js(__('admin.dashboard.monthly')), '#0b2a5b');
    const t = document.getElementById('typeChart');
    if (t) new Chart(t, { type: 'doughnut', data: { labels: @js($typeChart['labels']), datasets: [{ data: @js($typeChart['counts']), backgroundColor: ['#0b2a5b', '#0e7490', '#64748b'] }] }, options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } } });
});
</script>
@endpush
