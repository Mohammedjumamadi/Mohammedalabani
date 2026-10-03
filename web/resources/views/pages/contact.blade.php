@extends('layouts.public')

@section('title', __('pages.contact.title'))

@push('head')
<style>
/* ==========================================================================
   Contact Page Self-Contained Styles
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

.contact-card {
    background: #fff;
    border: 1px solid var(--aimv-border, #dde3ee);
    border-radius: var(--aimv-radius, 0.875rem);
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(11, 42, 91, 0.07);
}

.contact-info-strip {
    background: var(--aimv-primary, #0b2a5b);
    display: flex;
    justify-content: center;
    gap: 2rem;
    padding: 0.875rem 1.5rem;
    flex-wrap: wrap;
}

.contact-info-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: rgba(255, 255, 255, 0.85);
}
.contact-info-item i { color: var(--aimv-accent-bright, #06b6d4); font-size: 0.9rem; }

.contact-form .form-control,
.contact-form .form-select {
    border-color: var(--aimv-border, #dde3ee);
    border-radius: 0.625rem;
    padding: 0.625rem 0.875rem;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.contact-form .form-control:focus {
    border-color: var(--aimv-accent, #0e7490);
    box-shadow: 0 0 0 3px rgba(14, 116, 144, 0.12);
}

.contact-textarea { resize: vertical; min-height: 9rem; }

.contact-submit-btn {
    background: linear-gradient(135deg, var(--aimv-primary, #0b2a5b) 0%, var(--aimv-accent, #0e7490) 100%);
    border: none;
    border-radius: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    transition: opacity 0.2s, transform 0.2s, box-shadow 0.2s;
}
.contact-submit-btn:hover {
    opacity: 0.92;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(11, 42, 91, 0.25);
}
</style>
@endpush

@section('content')

{{-- ===== PAGE HERO ===== --}}
<section class="page-hero-band">
    <div class="container py-5">
        <div class="d-flex align-items-center gap-2 mb-3">
            <span class="page-hero-icon"><i class="bi bi-envelope-fill" aria-hidden="true"></i></span>
            <span class="page-hero-badge">{{ __('pages.contact.title') }}</span>
        </div>
        <h1 class="page-hero-title">{{ __('pages.contact.title') }}</h1>
        <p class="page-hero-lead">{{ __('pages.contact.intro') }}</p>
    </div>
</section>

<div class="container section" style="max-width:44rem">

    @include('partials.flash')

    {{-- Contact Card --}}
    <div class="contact-card">

        {{-- Info strip --}}
        <div class="contact-info-strip">
            <div class="contact-info-item">
                <i class="bi bi-clock-fill" aria-hidden="true"></i>
                <span class="small">استجابة خلال 24 ساعة</span>
            </div>
            <div class="contact-info-item">
                <i class="bi bi-shield-check-fill" aria-hidden="true"></i>
                <span class="small">بيانات آمنة ومشفرة</span>
            </div>
            <div class="contact-info-item">
                <i class="bi bi-chat-heart-fill" aria-hidden="true"></i>
                <span class="small">دعم متخصص</span>
            </div>
        </div>

        {{-- Form --}}
        <form method="POST" action="{{ route('pages.contact.send') }}" class="contact-form p-4" novalidate id="contactForm">
            @csrf

            <div class="row g-3">
                <div class="col-sm-6">
                    <x-form.input name="name" :label="__('pages.contact.name')" :value="auth()->user()?->name" required autocomplete="name" />
                </div>
                <div class="col-sm-6">
                    <x-form.input name="email" type="email" :label="__('pages.contact.email')" :value="auth()->user()?->email" required autocomplete="email" ltr />
                </div>
            </div>

            <div class="mt-3">
                <x-form.input name="subject" :label="__('pages.contact.subject')" required />
            </div>

            <div class="mb-4 mt-3">
                <label for="message" class="form-label fw-semibold">
                    {{ __('pages.contact.message') }}
                    <span class="text-danger" aria-hidden="true">*</span>
                </label>
                <textarea
                    id="message"
                    name="message"
                    rows="6"
                    required
                    placeholder="اكتب رسالتك هنا..."
                    @class(['form-control contact-textarea', 'is-invalid' => $errors->has('message')])
                >{{ old('message') }}</textarea>
                @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="btn btn-primary btn-lg w-100 contact-submit-btn" id="contactSubmitBtn">
                <i class="bi bi-send-fill me-2" aria-hidden="true"></i>
                {{ __('pages.contact.send') }}
            </button>
        </form>
    </div>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Form submit loading state
    const form = document.getElementById('contactForm');
    const btn = document.getElementById('contactSubmitBtn');
    if (form && btn) {
        form.addEventListener('submit', function () {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> جاري الإرسال...';
        });
    }
});
</script>
@endpush
