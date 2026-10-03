@extends('layouts.public')

@section('title', __('pages.awareness.title'))

@push('head')
<style>
/* ==========================================================================
   Awareness Page Self-Contained Styles
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

.awareness-article-card {
    display: flex;
    gap: 1.25rem;
    background: #fff;
    border: 1px solid var(--aimv-border, #dde3ee);
    border-radius: var(--aimv-radius, 0.875rem);
    padding: 1.5rem;
    margin-bottom: 1.25rem;
    box-shadow: var(--aimv-shadow, 0 1px 2px rgba(11, 42, 91, 0.06), 0 4px 14px rgba(11, 42, 91, 0.05));
    transition: box-shadow 0.2s, transform 0.2s;
}
.awareness-article-card:hover {
    box-shadow: 0 6px 24px rgba(11, 42, 91, 0.1);
    transform: translateY(-2px);
}

.awareness-article-num {
    width: 2.4rem;
    height: 2.4rem;
    background: var(--aimv-primary-50, #eef3fb);
    color: var(--aimv-primary, #0b2a5b);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 0.9rem;
    flex-shrink: 0;
    border: 2px solid var(--aimv-border, #dde3ee);
    margin-top: 0.15rem;
}
.awareness-article-body { flex: 1; }

.awareness-empty-state {
    text-align: center;
    padding: 4rem 1rem;
    background: var(--aimv-surface, #f6f8fc);
    border-radius: var(--aimv-radius, 0.875rem);
    border: 1px dashed var(--aimv-border, #dde3ee);
}

.awareness-cta-box {
    background: linear-gradient(135deg, var(--aimv-primary-50, #eef3fb) 0%, #ecfeff 100%);
    border: 1px solid var(--aimv-border, #dde3ee);
    border-radius: var(--aimv-radius, 0.875rem);
    padding: 1.5rem;
    margin-bottom: 2rem;
}
</style>
@endpush

@section('content')

{{-- ===== PAGE HERO ===== --}}
<section class="page-hero-band">
    <div class="container py-5">
        <div class="d-flex align-items-center gap-2 mb-3">
            <span class="page-hero-icon"><i class="bi bi-megaphone-fill" aria-hidden="true"></i></span>
            <span class="page-hero-badge">{{ __('pages.awareness.title') }}</span>
        </div>
        <h1 class="page-hero-title">{{ __('pages.awareness.title') }}</h1>
        <p class="page-hero-lead">{{ __('pages.awareness.intro') }}</p>
    </div>
</section>

<div class="container section" style="max-width:56rem">

    @forelse ($articles as $idx => $article)
        <article class="awareness-article-card">
            <div class="awareness-article-num" aria-hidden="true">{{ $idx + 1 }}</div>
            <div class="awareness-article-body">
                <h2 class="h5 mb-3 text-primary fw-bold">{{ $article['title'] }}</h2>
                @foreach (preg_split("/\R{2,}/", trim($article['body'])) as $paragraph)
                    <p class="mb-2 text-muted">{{ $paragraph }}</p>
                @endforeach
            </div>
        </article>
    @empty
        <div class="awareness-empty-state">
            <i class="bi bi-megaphone display-3 text-muted" aria-hidden="true"></i>
            <p class="mt-3 text-muted mb-0">{{ __('pages.awareness.empty') }}</p>
        </div>
    @endforelse

    {{-- CTA Section --}}
    @if (count($articles))
        <div class="awareness-cta-box mt-5">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <i class="bi bi-shield-exclamation display-6 text-accent" aria-hidden="true"></i>
                <div>
                    <div class="fw-semibold">هل تشك في محتوى ما؟</div>
                    <div class="small text-muted">ارفع الملف وتحقق منه بالذكاء الاصطناعي الآن</div>
                </div>
                <a href="{{ auth()->check() ? route('verifications.create') : route('register') }}" class="btn btn-primary ms-auto">
                    <i class="bi bi-search me-1" aria-hidden="true"></i>
                    ابدأ التحقق
                </a>
            </div>
        </div>
    @endif

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Quick reading time indicator for awareness articles
    document.querySelectorAll('.awareness-article-card').forEach(card => {
        const text = card.querySelector('.awareness-article-body')?.innerText || '';
        const words = text.trim().split(/\s+/).length;
        const readMinutes = Math.max(1, Math.ceil(words / 150));
        const numBadge = card.querySelector('.awareness-article-num');
        if (numBadge) {
            numBadge.title = `وقت القراءة التقديري: ${readMinutes} دقيقة`;
        }
    });
});
</script>
@endpush
