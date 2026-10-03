@extends('layouts.base')

@push('head')
    @if (! empty($noindex))
        <meta name="robots" content="noindex, nofollow">
    @endif
@endpush

@section('body')
<header class="aimv-navbar no-print">
    <nav class="navbar navbar-expand-xl" aria-label="{{ __('nav.menu') }}">
        <div class="container">
            <a class="navbar-brand" href="{{ route('home') }}">
                <span class="brand-mark" aria-hidden="true"><i class="bi bi-shield-check"></i></span>
                {{ __('app.name') }}
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav" aria-controls="publicNav" aria-expanded="false" aria-label="{{ __('nav.menu') }}">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="publicNav">
                @php
                    // نجمع صفحات التعريف الثلاث في قائمة منسدلة واحدة لتفادي ازدحام الشريط والتفاف النص
                    $aboutRoutes = ['pages.about', 'pages.how', 'pages.supported'];
                @endphp
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link @if (request()->routeIs('home')) active @endif" href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif>{{ __('nav.home') }}</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle @if (request()->routeIs($aboutRoutes)) active @endif" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            {{ __('pages.nav.group') }}
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('pages.about') }}">{{ __('pages.nav.about') }}</a></li>
                            <li><a class="dropdown-item" href="{{ route('pages.how') }}">{{ __('pages.nav.how') }}</a></li>
                            <li><a class="dropdown-item" href="{{ route('pages.supported') }}">{{ __('pages.nav.supported') }}</a></li>
                        </ul>
                    </li>
                    @foreach ([['pages.awareness', 'nav.awareness'], ['pages.faq', 'nav.faq'], ['pages.contact', 'pages.nav.contact']] as [$route, $label])
                        <li class="nav-item">
                            <a class="nav-link @if (request()->routeIs($route)) active @endif" href="{{ route($route) }}" @if (request()->routeIs($route)) aria-current="page" @endif>{{ __($label) }}</a>
                        </li>
                    @endforeach
                </ul>
                <div class="d-flex gap-2 align-items-center flex-nowrap auth-actions">
                    @include('partials.lang-switch')
                    @auth
                        <a href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : route('dashboard') }}" class="btn btn-primary btn-sm">{{ __('nav.dashboard') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-outline-primary btn-sm">{{ __('auth.login.title') }}</a>
                        <a href="{{ route('register') }}" class="btn btn-primary btn-sm">{{ __('auth.register.title') }}</a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>
</header>

<main id="main">
    @if (session('success') || session('status') || session('error'))
        <div class="container pt-3">@include('partials.flash')</div>
    @endif
    @yield('content')
</main>

<footer class="footer-site no-print">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-5">
                <div class="d-flex align-items-center gap-2 mb-2 text-white fw-bold">
                    <span class="brand-mark" aria-hidden="true"><i class="bi bi-shield-check"></i></span> {{ __('app.name') }}
                </div>
                <p class="small mb-0">{{ __('pages.footer.about') }}</p>
            </div>
            <div class="col-6 col-md-3">
                <h3>{{ __('pages.footer.platform') }}</h3>
                <ul class="list-unstyled small d-grid gap-1">
                    <li><a href="{{ route('pages.how') }}">{{ __('pages.nav.how') }}</a></li>
                    <li><a href="{{ route('pages.supported') }}">{{ __('pages.nav.supported') }}</a></li>
                    <li><a href="{{ route('pages.awareness') }}">{{ __('nav.awareness') }}</a></li>
                    <li><a href="{{ route('reports.verify') }}">{{ __('pages.footer.verify_report') }}</a></li>
                </ul>
            </div>
            <div class="col-6 col-md-4">
                <h3>{{ __('pages.footer.legal') }}</h3>
                <ul class="list-unstyled small d-grid gap-1">
                    <li><a href="{{ route('pages.privacy') }}">{{ __('pages.privacy.title') }}</a></li>
                    <li><a href="{{ route('pages.terms') }}">{{ __('pages.terms.title') }}</a></li>
                    <li><a href="{{ route('pages.faq') }}">{{ __('nav.faq') }}</a></li>
                    <li><a href="{{ route('pages.contact') }}">{{ __('pages.nav.contact') }}</a></li>
                </ul>
            </div>
        </div>
        <hr class="border-light opacity-25">
        <p class="small mb-0 text-center">{{ __('pages.footer.disclaimer') }}</p>
    </div>
</footer>
@endsection
