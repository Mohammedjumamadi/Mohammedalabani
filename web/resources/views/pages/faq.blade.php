@extends('layouts.public')

@section('title', __('pages.faq.title'))

@push('head')
<style>
/* ==========================================================================
   FAQ Page Self-Contained Styles
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

.faq-q-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.6rem;
    height: 1.6rem;
    background: var(--aimv-primary, #0b2a5b);
    color: #fff;
    border-radius: 50%;
    font-size: 0.7rem;
    font-weight: 700;
    flex-shrink: 0;
    margin-inline-end: 0.75rem;
    transition: background 0.2s;
}
.aimv-accordion-item { border: none; margin-bottom: 0.6rem; }
.aimv-accordion-btn {
    background: #fff;
    border: 1px solid var(--aimv-border, #dde3ee);
    border-radius: var(--aimv-radius, 0.875rem) !important;
    color: var(--aimv-primary, #0b2a5b);
    font-weight: 600;
    padding: 1rem 1.25rem;
    box-shadow: var(--aimv-shadow, 0 1px 2px rgba(11, 42, 91, 0.06), 0 4px 14px rgba(11, 42, 91, 0.05));
    gap: 0;
    display: flex;
    align-items: center;
    transition: background 0.2s, border-color 0.2s;
}
.aimv-accordion-btn:not(.collapsed) {
    background: var(--aimv-primary-50, #eef3fb);
    border-color: var(--aimv-accent, #0e7490);
    border-bottom-left-radius: 0 !important;
    border-bottom-right-radius: 0 !important;
    color: var(--aimv-primary, #0b2a5b);
    box-shadow: none;
}
.aimv-accordion-btn:not(.collapsed) .faq-q-num { background: var(--aimv-accent, #0e7490); }
.aimv-accordion-btn::after { margin-inline-start: auto; flex-shrink: 0; }
.aimv-accordion-body {
    background: var(--aimv-primary-50, #eef3fb);
    border: 1px solid var(--aimv-accent, #0e7490);
    border-top: none;
    border-bottom-left-radius: var(--aimv-radius, 0.875rem);
    border-bottom-right-radius: var(--aimv-radius, 0.875rem);
    padding: 1rem 1.25rem;
    color: var(--aimv-muted, #4b5563);
    line-height: 1.8;
}

.faq-cta-box {
    background: var(--aimv-surface, #f6f8fc);
    border: 1px solid var(--aimv-border, #dde3ee);
    border-radius: var(--aimv-radius, 0.875rem);
    padding: 1.25rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.faq-empty-state {
    text-align: center;
    padding: 4rem 1rem;
    background: var(--aimv-surface, #f6f8fc);
    border-radius: var(--aimv-radius, 0.875rem);
    border: 1px dashed var(--aimv-border, #dde3ee);
}
</style>
@endpush

@section('content')

{{-- ===== PAGE HERO ===== --}}
<section class="page-hero-band">
    <div class="container py-5">
        <div class="d-flex align-items-center gap-2 mb-3">
            <span class="page-hero-icon"><i class="bi bi-question-circle-fill" aria-hidden="true"></i></span>
            <span class="page-hero-badge">{{ __('pages.faq.title') }}</span>
        </div>
        <h1 class="page-hero-title">{{ __('pages.faq.title') }}</h1>
        <p class="page-hero-lead">{{ __('pages.faq.intro') }}</p>
    </div>
</section>

<div class="container section" style="max-width:52rem">

    @if (count($items))
        <div class="aimv-accordion mt-2" id="faq">
            @foreach ($items as $i => $item)
                <div class="accordion-item aimv-accordion-item">
                    <h2 class="accordion-header">
                        <button
                            class="accordion-button aimv-accordion-btn @if (! $loop->first) collapsed @endif"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#faq-{{ $i }}"
                            aria-expanded="{{ $loop->first ? 'true' : 'false' }}"
                            aria-controls="faq-{{ $i }}"
                        >
                            <span class="faq-q-num">{{ $i + 1 }}</span>
                            <span>{{ $item['title'] }}</span>
                        </button>
                    </h2>
                    <div
                        id="faq-{{ $i }}"
                        class="accordion-collapse collapse @if ($loop->first) show @endif"
                        data-bs-parent="#faq"
                    >
                        <div class="accordion-body aimv-accordion-body">
                            <i class="bi bi-chat-text-fill text-accent me-2" aria-hidden="true"></i>
                            {{ $item['body'] }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- CTA below FAQ --}}
        <div class="faq-cta-box mt-5">
            <i class="bi bi-envelope-fill text-accent fs-4" aria-hidden="true"></i>
            <div>
                <div class="fw-semibold">{{ __('pages.contact.title') }}</div>
                <div class="small text-muted">{{ __('pages.contact.intro') }}</div>
            </div>
            <a href="{{ route('pages.contact') }}" class="btn btn-sm btn-primary ms-auto">
                {{ __('pages.contact.send') }} <i class="bi bi-arrow-left" aria-hidden="true"></i>
            </a>
        </div>

    @else
        <div class="faq-empty-state">
            <i class="bi bi-chat-dots display-3 text-muted" aria-hidden="true"></i>
            <p class="mt-3 text-muted">{{ __('pages.faq.empty') }}</p>
            <a href="{{ route('pages.contact') }}" class="btn btn-outline-primary">{{ __('pages.contact.title') }}</a>
        </div>
    @endif

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // FAQ auto-scroll to open item on mobile
    const buttons = document.querySelectorAll('.aimv-accordion-btn');
    buttons.forEach(btn => {
        btn.addEventListener('click', function () {
            setTimeout(() => {
                if (!this.classList.contains('collapsed')) {
                    this.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }, 250);
        });
    });
});
</script>
@endpush
