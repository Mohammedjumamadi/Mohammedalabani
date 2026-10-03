@extends('layouts.public')

@section('title', __('pages.supported.title'))

@push('head')
<style>
/* ==========================================================================
   Supported Content Page Self-Contained Styles
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

.supported-type-card {
    background: #fff;
    border: 1px solid var(--aimv-border, #dde3ee);
    border-radius: var(--aimv-radius, 0.875rem);
    padding: 2rem 1.5rem;
    height: 100%;
    text-align: center;
    box-shadow: var(--aimv-shadow, 0 1px 2px rgba(11, 42, 91, 0.06), 0 4px 14px rgba(11, 42, 91, 0.05));
    transition: box-shadow 0.2s, transform 0.2s;
}
.supported-type-card:hover {
    box-shadow: 0 8px 30px rgba(11, 42, 91, 0.12);
    transform: translateY(-4px);
}

.supported-type-icon {
    width: 4rem;
    height: 4rem;
    background: var(--aimv-primary, #0b2a5b);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    color: #fff;
    margin: 0 auto;
    box-shadow: 0 4px 16px rgba(11, 42, 91, 0.25);
}

.supported-formats-label {
    color: var(--aimv-muted, #4b5563);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-size: 0.72rem;
}

.supported-ext-badge {
    display: inline-block;
    background: var(--aimv-surface, #f6f8fc);
    border: 1px solid var(--aimv-border, #dde3ee);
    color: var(--aimv-primary, #0b2a5b);
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.18rem 0.55rem;
    border-radius: 0.4rem;
    letter-spacing: 0.04em;
    transition: background 0.15s, border-color 0.15s;
}
.supported-ext-badge:hover {
    background: var(--aimv-primary-50, #eef3fb);
    border-color: var(--aimv-primary, #0b2a5b);
}

.supported-notice-box {
    background: var(--aimv-accent-50, #ecfeff);
    border: 1px solid rgba(14, 116, 144, 0.3);
    border-radius: var(--aimv-radius, 0.875rem);
    padding: 1.25rem 1.5rem;
    margin-bottom: 2rem;
}
</style>
@endpush

@section('content')

{{-- ===== PAGE HERO ===== --}}
<section class="page-hero-band">
    <div class="container py-5">
        <div class="d-flex align-items-center gap-2 mb-3">
            <span class="page-hero-icon"><i class="bi bi-file-earmark-check-fill" aria-hidden="true"></i></span>
            <span class="page-hero-badge">{{ __('pages.supported.title') }}</span>
        </div>
        <h1 class="page-hero-title">{{ __('pages.supported.title') }}</h1>
        <p class="page-hero-lead">{{ __('pages.supported.intro') }}</p>
    </div>
</section>

<div class="container section" style="max-width:60rem">

    {{-- Media Type Cards --}}
    <div class="row g-4 mb-4">
        @foreach (\App\Enums\MediaType::cases() as $type)
        <div class="col-md-4">
            <div class="supported-type-card">
                <div class="supported-type-icon">
                    <i class="bi {{ $type->icon() }}" aria-hidden="true"></i>
                </div>
                <h2 class="h5 mb-2 mt-3">{{ $type->label() }}</h2>
                <p class="text-muted small mb-3">{{ __('pages.supported.'.$type->value) }}</p>
                <div class="supported-formats-label small fw-semibold mb-2">{{ __('pages.supported.formats') }}</div>
                <div class="d-flex flex-wrap gap-1 justify-content-center">
                    @foreach ($types[$type->value] as $ext)
                        <span class="supported-ext-badge ltr-text">{{ strtoupper($ext) }}</span>
                    @endforeach
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Size & Rules Notice --}}
    <div class="supported-notice-box">
        <div class="d-flex gap-3 align-items-start">
            <i class="bi bi-info-circle-fill text-accent fs-4 flex-shrink-0 mt-1" aria-hidden="true"></i>
            <div>
                <div class="fw-semibold mb-1">{{ __('pages.supported.max_size', ['size' => $maxLabel]) }}</div>
                <div class="small text-muted">{{ __('pages.supported.not_allowed') }}</div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Copy extension badge on click helper
    document.querySelectorAll('.supported-ext-badge').forEach(badge => {
        badge.style.cursor = 'default';
    });
});
</script>
@endpush
