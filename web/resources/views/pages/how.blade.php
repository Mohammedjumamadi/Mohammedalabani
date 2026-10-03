@extends('layouts.public')

@section('title', __('pages.how.title'))

@php $t = app(\App\Services\Verification\ClassificationSettings::class)->thresholds(); @endphp

@push('head')
<style>
/* ==========================================================================
   How Page Self-Contained Styles
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

.how-timeline {
    list-style: none;
    padding: 0;
    margin: 0 0 2rem;
    position: relative;
}
.how-timeline::before {
    content: '';
    position: absolute;
    inset-inline-start: 1.4rem;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(to bottom, var(--aimv-accent, #0e7490) 0%, var(--aimv-border, #dde3ee) 100%);
}
[dir="ltr"] .how-timeline::before { left: 1.4rem; }

.how-timeline-item {
    display: flex;
    gap: 1.25rem;
    align-items: flex-start;
    margin-bottom: 1.25rem;
    position: relative;
}

.how-timeline-marker {
    width: 2.8rem;
    height: 2.8rem;
    background: var(--aimv-primary, #0b2a5b);
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 1rem;
    flex-shrink: 0;
    position: relative;
    z-index: 1;
    box-shadow: 0 0 0 4px var(--aimv-accent-50, #ecfeff), 0 2px 8px rgba(11, 42, 91, 0.2);
    transition: background 0.2s, transform 0.2s;
}
.how-timeline-item:hover .how-timeline-marker {
    background: var(--aimv-accent, #0e7490);
    transform: scale(1.08);
}

.how-timeline-card {
    background: #fff;
    border: 1px solid var(--aimv-border, #dde3ee);
    border-radius: var(--aimv-radius, 0.875rem);
    padding: 1rem 1.25rem;
    flex: 1;
    box-shadow: var(--aimv-shadow, 0 1px 2px rgba(11, 42, 91, 0.06), 0 4px 14px rgba(11, 42, 91, 0.05));
    transition: box-shadow 0.2s, transform 0.2s;
}
.how-timeline-item:hover .how-timeline-card {
    box-shadow: 0 6px 24px rgba(11, 42, 91, 0.1);
    transform: translateX(-2px);
}
[dir="ltr"] .how-timeline-item:hover .how-timeline-card { transform: translateX(2px); }

.how-verdict-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.1rem 1.4rem;
    border-radius: var(--aimv-radius, 0.875rem);
    border: 1.5px solid;
    flex-wrap: wrap;
    transition: transform 0.2s ease;
}
.how-verdict-card:hover {
    transform: translateY(-2px);
}
.how-verdict-fake    { background: var(--aimv-fake-bg, #fef2f2); border-color: var(--aimv-fake, #b91c1c); color: var(--aimv-fake, #b91c1c); }
.how-verdict-uncertain { background: var(--aimv-uncertain-bg, #fffbeb); border-color: var(--aimv-uncertain, #b45309); color: var(--aimv-uncertain, #b45309); }
.how-verdict-real    { background: var(--aimv-real-bg, #f0fdf4); border-color: var(--aimv-real, #15803d); color: var(--aimv-real, #15803d); }

.how-verdict-icon {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.how-verdict-fake .how-verdict-icon    { background: var(--aimv-fake-bg, #fef2f2); border: 2px solid var(--aimv-fake, #b91c1c); color: var(--aimv-fake, #b91c1c); }
.how-verdict-uncertain .how-verdict-icon { background: var(--aimv-uncertain-bg, #fffbeb); border: 2px solid var(--aimv-uncertain, #b45309); color: var(--aimv-uncertain, #b45309); }
.how-verdict-real .how-verdict-icon    { background: var(--aimv-real-bg, #f0fdf4); border: 2px solid var(--aimv-real, #15803d); color: var(--aimv-real, #15803d); }

.how-verdict-card > div:first-of-type + div { flex: 1; color: var(--aimv-text, #1f2937); }
.how-verdict-pct {
    font-size: 0.85rem;
    font-weight: 700;
    background: rgba(255, 255, 255, 0.7);
    border-radius: 0.5rem;
    padding: 0.2rem 0.6rem;
    white-space: nowrap;
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
            <span class="page-hero-icon"><i class="bi bi-diagram-3-fill" aria-hidden="true"></i></span>
            <span class="page-hero-badge">{{ __('pages.how.title') }}</span>
        </div>
        <h1 class="page-hero-title">{{ __('pages.how.title') }}</h1>
        <p class="page-hero-lead">{{ __('pages.how.intro') }}</p>
    </div>
</section>

<div class="container section" style="max-width:56rem">

    {{-- ===== STEPS TIMELINE ===== --}}
    <ol class="how-timeline" aria-label="{{ __('pages.how.title') }}">
        @foreach (__('pages.how.steps') as $i => $step)
            <li class="how-timeline-item">
                <div class="how-timeline-marker" aria-hidden="true">{{ $i + 1 }}</div>
                <div class="how-timeline-card">
                    <h2 class="h5 mb-1">{{ $step['h'] }}</h2>
                    <p class="mb-0 text-muted">{{ $step['p'] }}</p>
                </div>
            </li>
        @endforeach
    </ol>

    {{-- ===== CLASSIFICATION SECTION ===== --}}
    <section class="mt-5 mb-5" aria-labelledby="classes-title">
        <div class="text-center mb-4">
            <span class="section-tag-pill"><i class="bi bi-bar-chart-steps" aria-hidden="true"></i> {{ __('pages.how.classes_title') }}</span>
            <p class="text-muted mt-2 mb-0 small">{{ __('pages.how.classes_help') }}</p>
        </div>

        <div class="d-grid gap-3">
            <div class="how-verdict-card how-verdict-fake">
                <div class="how-verdict-icon">
                    <i class="bi bi-x-octagon-fill" aria-hidden="true"></i>
                </div>
                <div>
                    <div class="fw-bold mb-1">{{ \App\Enums\Classification::Fake->label() }}</div>
                    <div class="small">{{ __('pages.how.class_fake', ['fake' => $t['fake']]) }}</div>
                </div>
                <div class="how-verdict-pct ms-auto ltr-text">≥ {{ $t['fake'] }}%</div>
            </div>

            <div class="how-verdict-card how-verdict-uncertain">
                <div class="how-verdict-icon">
                    <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                </div>
                <div>
                    <div class="fw-bold mb-1">{{ \App\Enums\Classification::Uncertain->label() }}</div>
                    <div class="small">{{ __('pages.how.class_uncertain', ['real' => $t['real'], 'below' => $t['fake'] - 1]) }}</div>
                </div>
                <div class="how-verdict-pct ms-auto ltr-text">{{ $t['real'] }}–{{ $t['fake'] - 1 }}%</div>
            </div>

            <div class="how-verdict-card how-verdict-real">
                <div class="how-verdict-icon">
                    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                </div>
                <div>
                    <div class="fw-bold mb-1">{{ \App\Enums\Classification::Real->label() }}</div>
                    <div class="small">{{ __('pages.how.class_real', ['real' => $t['real']]) }}</div>
                </div>
                <div class="how-verdict-pct ms-auto ltr-text">< {{ $t['real'] }}%</div>
            </div>
        </div>

        <p class="mt-3 small text-muted">
            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
            {{ __('pages.how.classes_note') }}
        </p>
    </section>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Interactive timeline step focus
    const timelineItems = document.querySelectorAll('.how-timeline-item');
    timelineItems.forEach(item => {
        item.addEventListener('mouseenter', () => {
            timelineItems.forEach(i => i.classList.remove('opacity-75'));
        });
    });
});
</script>
@endpush
