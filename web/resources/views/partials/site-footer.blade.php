{{-- تذييل الواجهة العامة --}}
<footer class="site-footer no-print">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-5">
                <a class="site-brand mb-3" href="{{ route('home') }}">
                    <span class="site-brand-mark">
                        <img src="{{ asset('image/photo_2026-09-24_13-06-22.jpg') }}" alt="{{ __('landing.logo_alt') }}" width="179" height="60" loading="lazy">
                    </span>
                    <span class="site-brand-name" dir="ltr">{{ __('landing.site_name') }}</span>
                </a>
                <p class="mb-0">{{ __('landing.footer.about') }}</p>
            </div>
            <div class="col-6 col-lg-3 offset-lg-1">
                <h2 class="site-footer-title">{{ __('landing.footer.platform') }}</h2>
                <ul class="list-unstyled d-grid gap-2 mb-0">
                    <li><a href="{{ route('pages.how') }}">{{ __('landing.nav.how') }}</a></li>
                    <li><a href="{{ route('pages.supported') }}">{{ __('landing.nav.supported') }}</a></li>
                    <li><a href="{{ route('pages.awareness') }}">{{ __('landing.nav.awareness') }}</a></li>
                    <li><a href="{{ route('reports.verify') }}">{{ __('pages.footer.verify_report') }}</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-3">
                <h2 class="site-footer-title">{{ __('landing.footer.legal') }}</h2>
                <ul class="list-unstyled d-grid gap-2 mb-0">
                    <li><a href="{{ route('pages.privacy') }}">{{ __('pages.privacy.title') }}</a></li>
                    <li><a href="{{ route('pages.terms') }}">{{ __('pages.terms.title') }}</a></li>
                    <li><a href="{{ route('pages.faq') }}">{{ __('landing.nav.faq') }}</a></li>
                    <li><a href="{{ route('pages.contact') }}">{{ __('landing.nav.contact') }}</a></li>
                </ul>
            </div>
        </div>
        <div class="site-footer-bottom">
            <p class="mb-1">{{ __('pages.footer.disclaimer') }}</p>
            <p class="mb-0">{{ __('landing.footer.rights', ['year' => now()->year]) }}</p>
        </div>
    </div>
</footer>
