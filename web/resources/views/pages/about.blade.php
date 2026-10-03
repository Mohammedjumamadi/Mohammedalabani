@extends('layouts.public')

@section('title', __('pages.about.title'))

@push('head')
<style>
/* ==========================================================================
   About Page Self-Contained Styles
   ========================================================================== */
.page-hero-band {
    background: linear-gradient(135deg, var(--aimv-primary, #0b2a5b) 0%, #0a2a6b 60%, #0e3870 100%);
    position: relative;
    overflow: hidden;
}
.page-hero-band::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 70% 50%, rgba(6, 182, 212, 0.12) 0%, transparent 60%),
                radial-gradient(circle at 10% 90%, rgba(14, 116, 144, 0.08) 0%, transparent 50%);
    pointer-events: none;
}
.page-hero-band .container { position: relative; z-index: 1; }

.page-hero-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 50%;
    color: #fff;
    font-size: 1rem;
    backdrop-filter: blur(6px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}
.page-hero-badge {
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.75);
}
.page-hero-title {
    font-size: clamp(1.6rem, 4vw, 2.4rem);
    font-weight: 800;
    color: #fff;
    line-height: 1.25;
    margin-bottom: 0.75rem;
}
.page-hero-lead {
    font-size: 1rem;
    color: rgba(255, 255, 255, 0.75);
    max-width: 52rem;
    margin-bottom: 0;
}

.about-section-card {
    background: #fff;
    border: 1px solid var(--aimv-border, #dde3ee);
    border-radius: var(--aimv-radius, 0.875rem);
    padding: 1.75rem 1.5rem;
    height: 100%;
    transition: box-shadow 0.2s, transform 0.2s;
    box-shadow: var(--aimv-shadow, 0 1px 2px rgba(11, 42, 91, 0.06), 0 4px 14px rgba(11, 42, 91, 0.05));
}
.about-section-card:hover {
    box-shadow: 0 8px 32px rgba(11, 42, 91, 0.1);
    transform: translateY(-3px);
}
.about-section-icon {
    font-size: 2rem;
    line-height: 1;
}

.type-badge-pill {
    background: var(--aimv-primary-50, #eef3fb);
    color: var(--aimv-primary, #0b2a5b);
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.2rem 0.65rem;
    border-radius: 50rem;
    white-space: nowrap;
}
.metric-pill {
    display: inline-block;
    font-size: 0.78rem;
    font-weight: 700;
    padding: 0.15rem 0.55rem;
    border-radius: 50rem;
    letter-spacing: 0.01em;
}
.metric-high { background: var(--aimv-real-bg, #f0fdf4); color: var(--aimv-real, #15803d); }
.metric-mid  { background: var(--aimv-uncertain-bg, #fffbeb); color: var(--aimv-uncertain, #b45309); }
.metric-low  { background: var(--aimv-fake-bg, #fef2f2); color: var(--aimv-fake, #b91c1c); }

.about-metrics-empty {
    text-align: center;
    padding: 3rem 1rem;
    background: var(--aimv-surface, #f6f8fc);
    border-radius: var(--aimv-radius, 0.875rem);
    border: 1px dashed var(--aimv-border, #dde3ee);
}

.about-trust-banner {
    background: linear-gradient(135deg, var(--aimv-primary-50, #eef3fb) 0%, #f0f6ff 100%);
    border: 1px solid var(--aimv-border, #dde3ee);
    border-radius: var(--aimv-radius, 0.875rem);
    padding: 1.25rem 1.5rem;
    margin-bottom: 2rem;
}
.about-trust-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 3rem;
    height: 3rem;
    background: var(--aimv-primary, #0b2a5b);
    border-radius: 50%;
    color: #fff;
    font-size: 1.25rem;
    flex-shrink: 0;
}
.section-tag-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: var(--aimv-accent-50, #ecfeff);
    color: var(--aimv-accent, #0e7490);
    font-size: 0.8rem;
    font-weight: 700;
    padding: 0.3rem 0.9rem;
    border-radius: 50rem;
    border: 1px solid rgba(14, 116, 144, 0.2);
    letter-spacing: 0.02em;
}
</style>
@endpush

@section('content')

{{-- ===== PAGE HERO ===== --}}
<section class="page-hero-band">
    <div class="container py-5">
        <div class="d-flex align-items-center gap-2 mb-3">
            <span class="page-hero-icon"><i class="bi bi-info-circle-fill" aria-hidden="true"></i></span>
            <span class="page-hero-badge">{{ __('pages.about.title') }}</span>
        </div>
        <h1 class="page-hero-title">AI Media Verifier</h1>
        <p class="page-hero-lead">{{ __('pages.about.intro') }}</p>
    </div>
</section>

{{-- ===== ABOUT SECTIONS ===== --}}
<div class="container section" style="max-width:60rem">

    {{-- Text Sections --}}
    <div class="row g-4 mb-5">
        @foreach (__('pages.about.sections') as $idx => $section)
        @php
            $icons = ['bi-lightbulb-fill', 'bi-shield-fill-check', 'bi-patch-check-fill'];
            $colors = ['text-warning', 'text-danger', 'text-success'];
        @endphp
        <div class="col-md-4">
            <div class="about-section-card">
                <div class="about-section-icon {{ $colors[$idx] ?? 'text-primary' }}">
                    <i class="bi {{ $icons[$idx] ?? 'bi-circle-fill' }}" aria-hidden="true"></i>
                </div>
                <h2 class="h5 mt-3 mb-2">{{ $section['h'] }}</h2>
                @foreach ($section['p'] as $paragraph)
                    <p class="mb-0 small">{{ $paragraph }}</p>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>

    {{-- ===== METRICS SECTION ===== --}}
    <section id="metrics" aria-labelledby="metrics-title" class="mb-5">
        <div class="text-center mb-4">
            <span class="section-tag-pill"><i class="bi bi-graph-up" aria-hidden="true"></i> {{ __('pages.about.metrics_title') }}</span>
            <p class="text-muted mt-2 mb-0 small">{{ __('pages.about.metrics_help') }}</p>
        </div>

        @if ($models->isEmpty())
            <div class="about-metrics-empty">
                <i class="bi bi-clipboard-data display-4 text-muted d-block mb-3" aria-hidden="true"></i>
                <p class="text-muted mb-0">{{ __('pages.about.metrics_none') }}</p>
            </div>
        @else
            <div class="aimv-card overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-aimv align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col" id="metrics-title">{{ __('verifications.show.model_name') }}</th>
                                <th scope="col">{{ __('verifications.index.type') }}</th>
                                <th scope="col">{{ __('pages.about.accuracy') }}</th>
                                <th scope="col">{{ __('pages.about.precision') }}</th>
                                <th scope="col">{{ __('pages.about.recall') }}</th>
                                <th scope="col">{{ __('pages.about.f1') }}</th>
                                <th scope="col">{{ __('pages.about.dataset') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($models as $model)
                                @foreach ($model->metricsByDataset() as $m)
                                    <tr>
                                        <td class="ltr-text fw-semibold">{{ $model->name }} <span class="text-muted fw-normal">v{{ $model->version }}</span></td>
                                        <td><span class="type-badge-pill">{{ $model->media_type->label() }}</span></td>
                                        @foreach (['accuracy', 'precision', 'recall', 'f1'] as $metric)
                                            @php $val = isset($m[$metric]) ? $m[$metric] * 100 : null; @endphp
                                            <td class="ltr-text">
                                                @if($val !== null)
                                                    <span class="metric-pill {{ $val >= 90 ? 'metric-high' : ($val >= 75 ? 'metric-mid' : 'metric-low') }}">
                                                        {{ number_format($val, 1) }}%
                                                    </span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="small text-muted">{{ $m['dataset_label'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </section>

    {{-- ===== TRUST BANNER ===== --}}
    <div class="about-trust-banner">
        <div class="row align-items-center g-3">
            <div class="col-auto">
                <span class="about-trust-icon"><i class="bi bi-shield-lock-fill" aria-hidden="true"></i></span>
            </div>
            <div class="col">
                <div class="fw-semibold">{{ __('pages.about.sections')[2]['h'] ?? '' }}</div>
                <div class="small text-muted">{{ __('pages.about.sections')[2]['p'][0] ?? '' }}</div>
            </div>
            <div class="col-auto">
                <a href="{{ route('pages.how') }}" class="btn btn-sm btn-outline-primary">
                    {{ __('pages.home.cta_how') }} <i class="bi bi-arrow-left" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Highlight metrics on scroll anchor if #metrics is targeted
    if (window.location.hash === '#metrics') {
        const el = document.getElementById('metrics');
        if (el) {
            el.scrollIntoView({ behavior: 'smooth' });
        }
    }
});
</script>
@endpush
