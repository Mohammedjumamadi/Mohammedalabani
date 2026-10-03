@extends('layouts.panel')

@section('panel', 'admin')
@section('title', __('nav.admin.models'))

@section('content')
    <div class="page-head">
        <h1>{{ __('nav.admin.models') }}</h1>
        <form method="POST" action="{{ route('admin.models.refresh') }}">@csrf
            <button class="btn btn-outline-primary"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> {{ __('admin.models.refresh') }}</button></form>
    </div>

    <section class="aimv-card mb-4" aria-labelledby="hs">
        <div class="card-head"><h2 id="hs">{{ __('admin.models.service') }}</h2>
            <span class="verdict-badge {{ $health['online'] ? 'verdict-real' : 'verdict-fake' }}"><i class="bi {{ $health['online'] ? 'bi-check-circle-fill' : 'bi-x-octagon-fill' }}" aria-hidden="true"></i> {{ $health['online'] ? __('admin.dashboard.online') : __('admin.dashboard.offline') }}</span></div>
        <div class="card-body-pad small">
            @if ($health['online'])
                {{ __('admin.dashboard.ai_version') }}: <span class="ltr-text">{{ $health['data']['version'] ?? '—' }}</span> ·
                {{ __('admin.models.device') }}: <span class="ltr-text">{{ $health['data']['device'] ?? '—' }}</span> ·
                FFmpeg: {{ ($health['data']['ffmpeg'] ?? false) ? __('common.yes') : __('common.no') }}
            @else
                {{ __('admin.dashboard.ai_offline_help') }}
            @endif
        </div>
    </section>

    <section class="aimv-card mb-4" aria-labelledby="oc">
        <div class="card-head"><h2 id="oc">{{ __('admin.models.components') }}</h2></div>
        <div class="card-body-pad text-muted small pb-0">{{ __('admin.models.components_help') }}</div>
        <div class="table-responsive"><table class="table table-aimv align-middle mb-0"><caption class="visually-hidden">{{ __('admin.models.components') }}</caption>
            <thead><tr><th scope="col">{{ __('verifications.show.model_name') }}</th><th scope="col">{{ __('verifications.index.type') }}</th><th scope="col">{{ __('admin.models.license') }}</th><th scope="col">{{ __('admin.models.state') }}</th><th scope="col">{{ __('admin.models.revision') }}</th></tr></thead>
            <tbody>@forelse ($components as $c)
                <tr><td><a href="{{ $c['source'] }}" target="_blank" rel="noopener noreferrer" class="ltr-text">{{ $c['name'] }}</a><div class="small text-muted">{{ $c['description'] }}</div></td>
                    <td>{{ \App\Enums\MediaType::tryFrom($c['media_type'])?->label() }}</td><td class="ltr-text">{{ $c['license'] }}</td>
                    <td>{{ $c['loaded'] ? __('admin.models.loaded') : __('admin.models.not_loaded') }}</td><td class="ltr-text">{{ $c['version'] ?? '—' }}</td></tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">{{ __('admin.models.service_unreachable') }}</td></tr>
            @endforelse</tbody></table></div>
    </section>

    <h2 class="h5 mb-3">{{ __('admin.models.versions') }}</h2>
    @forelse ($versions as $v)
        <section class="aimv-card mb-4" aria-labelledby="v{{ $v->id }}">
            <div class="card-head">
                <h3 id="v{{ $v->id }}" class="h6"><span class="ltr-text">{{ $v->name }}</span> <span class="text-muted ltr-text">v{{ $v->version }}</span> · {{ $v->media_type->label() }}</h3>
                <div class="d-flex gap-2 align-items-center">
                    <span class="verdict-badge {{ $v->is_active ? 'verdict-real' : 'verdict-neutral' }}">{{ $v->is_active ? __('admin.models.active') : __('admin.models.inactive') }}</span>
                    <span class="small text-muted">{{ __('admin.models.used_in', ['count' => $v->results_count]) }}</span>
                    <form method="POST" action="{{ route('admin.models.toggle', $v) }}">@csrf<button class="btn btn-sm btn-outline-primary">{{ $v->is_active ? __('admin.models.deactivate') : __('admin.models.activate') }}</button></form>
                </div>
            </div>
            <div class="card-body-pad">
                <dl class="row small mb-3">
                    <dt class="col-sm-3 text-muted">{{ __('verifications.show.model_source') }}</dt><dd class="col-sm-9 text-break ltr-text">{{ $v->source_url ?: '—' }}</dd>
                    <dt class="col-sm-3 text-muted">{{ __('admin.models.license') }}</dt><dd class="col-sm-9 ltr-text">{{ $v->license ?: '—' }}</dd>
                </dl>
                @if ($v->hasMetrics())
                    @include('admin.partials.metrics', ['byDataset' => $v->metricsByDataset()])
                    <div class="small text-muted mt-2">{{ __('admin.models.evaluated_at', ['date' => $v->evaluated_at?->translatedFormat('j M Y')]) }} · {{ $v->evaluation_dataset }}</div>
                @else
                    <div class="notice-box">{{ __('admin.models.no_metrics') }}</div>
                @endif

                <form method="POST" action="{{ route('admin.models.metrics', $v) }}" enctype="multipart/form-data" class="mt-3 row g-2 align-items-end no-print">
                    @csrf
                    <div class="col-md-8">
                        <label class="form-label small mb-1" for="mf{{ $v->id }}">{{ __('admin.models.import_label') }}</label>
                        <input id="mf{{ $v->id }}" type="file" name="metrics_file" accept="application/json,.json" class="form-control @error('metrics_file') is-invalid @enderror" required>
                        @error('metrics_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4"><button class="btn btn-outline-primary w-100"><i class="bi bi-upload" aria-hidden="true"></i> {{ __('admin.models.import') }}</button></div>
                    <div class="col-12 form-text">{{ __('admin.models.import_help') }}</div>
                </form>
            </div>
        </section>
    @empty
        <div class="notice-box">{{ __('admin.models.none') }}</div>
    @endforelse
@endsection
