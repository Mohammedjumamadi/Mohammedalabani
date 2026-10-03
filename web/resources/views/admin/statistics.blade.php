@extends('layouts.panel')

@section('panel', 'admin')
@section('title', __('nav.admin.statistics'))

@section('content')
    <div class="page-head">
        <h1>{{ __('nav.admin.statistics') }}</h1>
        <a href="{{ route('admin.statistics.export') }}" class="btn btn-primary"><i class="bi bi-filetype-csv" aria-hidden="true"></i> {{ __('admin.statistics.export') }}</a>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-8"><section class="aimv-card h-100" aria-labelledby="s1">
            <div class="card-head"><h2 id="s1">{{ __('admin.statistics.daily30') }}</h2></div>
            <div class="card-body-pad"><div style="height:260px"><canvas id="dailyChart" role="img" aria-label="{{ __('admin.statistics.daily30') }}"></canvas></div></div>
        </section></div>
        <div class="col-lg-4"><section class="aimv-card h-100" aria-labelledby="s2">
            <div class="card-head"><h2 id="s2">{{ __('admin.statistics.by_class') }}</h2></div>
            <div class="card-body-pad"><div style="height:260px"><canvas id="classChart" role="img" aria-label="{{ __('admin.statistics.by_class') }}"></canvas></div></div>
        </section></div>
        <div class="col-12"><section class="aimv-card" aria-labelledby="s3">
            <div class="card-head"><h2 id="s3">{{ __('admin.statistics.monthly12') }}</h2></div>
            <div class="card-body-pad"><div style="height:260px"><canvas id="monthlyChart" role="img" aria-label="{{ __('admin.statistics.monthly12') }}"></canvas></div></div>
        </section></div>
    </div>

    <section class="aimv-card mb-4" aria-labelledby="s4">
        <div class="card-head"><h2 id="s4">{{ __('admin.statistics.per_type') }}</h2><span class="text-muted small">{{ __('admin.statistics.failed_rate', ['rate' => $failedRate]) }}</span></div>
        <div class="table-responsive"><table class="table table-aimv align-middle mb-0"><caption class="visually-hidden">{{ __('admin.statistics.per_type') }}</caption>
            <thead><tr><th scope="col">{{ __('verifications.index.type') }}</th><th scope="col">{{ __('dashboard.total') }}</th><th scope="col">{{ __('dashboard.failed') }}</th><th scope="col">{{ __('admin.dashboard.avg_time') }}</th></tr></thead>
            <tbody>@foreach ($perType as $row)
                <tr><td><i class="bi {{ $row['icon'] }} text-accent" aria-hidden="true"></i> {{ $row['label'] }}</td><td>{{ $row['total'] }}</td><td>{{ $row['failed'] }}</td>
                    <td>{{ $row['avg_time'] !== null ? __('verifications.show.seconds', ['value' => number_format($row['avg_time'], 1)]) : '—' }}</td></tr>
            @endforeach</tbody></table></div>
    </section>

    <section class="aimv-card" aria-labelledby="s5">
        <div class="card-head"><h2 id="s5">{{ __('admin.statistics.model_metrics') }}</h2></div>
        <div class="card-body-pad text-muted small pb-0">{{ __('admin.statistics.metrics_note') }}</div>
        @forelse ($models as $model)
            <div class="card-body-pad {{ $loop->last ? '' : 'border-bottom' }}">
                <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                    <div><strong class="ltr-text">{{ $model->name }}</strong> <span class="text-muted ltr-text">v{{ $model->version }}</span> · {{ $model->media_type->label() }}</div>
                    @if ($model->evaluated_at)<span class="small text-muted">{{ __('admin.models.evaluated_at', ['date' => $model->evaluated_at->translatedFormat('j M Y')]) }} · {{ $model->evaluation_dataset }}</span>@endif
                </div>
                @if ($model->hasMetrics())
                    @include('admin.partials.metrics', ['byDataset' => $model->metricsByDataset()])
                @else
                    <div class="notice-box">{{ __('admin.models.no_metrics') }}</div>
                @endif
            </div>
        @empty
            <div class="card-body-pad text-muted">{{ __('admin.models.none') }}</div>
        @endforelse
    </section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    Chart.defaults.font = { family: 'Cairo' };
    const bar = (id, labels, data, label, color) => { const el = document.getElementById(id); if (el) new Chart(el, { type: 'bar', data: { labels, datasets: [{ label, data, backgroundColor: color, borderRadius: 5 }] }, options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } } }); };
    bar('dailyChart', @js($daily['labels']), @js($daily['counts']), @js(__('admin.statistics.daily30')), '#0e7490');
    bar('monthlyChart', @js($monthly['labels']), @js($monthly['counts']), @js(__('admin.statistics.monthly12')), '#0b2a5b');
    const c = document.getElementById('classChart');
    if (c) new Chart(c, { type: 'doughnut', data: { labels: [@js(\App\Enums\Classification::Real->label()), @js(\App\Enums\Classification::Uncertain->label()), @js(\App\Enums\Classification::Fake->label())], datasets: [{ data: [{{ $classification['real'] }}, {{ $classification['uncertain'] }}, {{ $classification['fake'] }}], backgroundColor: ['#15803d', '#b45309', '#b91c1c'] }] }, options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } } });
});
</script>
@endpush
