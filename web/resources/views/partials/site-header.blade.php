{{-- شريط التنقل الموحّد للواجهة العامة (الأنماط: theme.css ← .site) --}}
@php
    $aboutRoutes = ['pages.about', 'pages.how', 'pages.supported'];
    $links = [
        ['pages.awareness', 'landing.nav.awareness'],
        ['pages.faq', 'landing.nav.faq'],
        ['pages.contact', 'landing.nav.contact'],
    ];
    $onLogin = request()->routeIs('login');
    $onRegister = request()->routeIs('register');
@endphp
<header class="site-nav no-print">
    <nav class="navbar navbar-expand-lg" aria-label="{{ __('landing.menu') }}">
        <div class="container">
            <a class="site-brand" href="{{ route('home') }}">
                {{-- الشعار المرفق كما هو: تُعرض منه أيقونة الدرع فقط بالقصّ عبر CSS، والاسم يُكتب نصًا بجانبه --}}
                <span class="site-brand-mark">
                    <img src="{{ asset('image/photo_2026-09-24_13-06-22.jpg') }}" alt="{{ __('landing.logo_alt') }}" width="179" height="60">
                </span>
                <span class="site-brand-name" dir="ltr">{{ __('landing.site_name') }}</span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#siteNav"
                    aria-controls="siteNav" aria-expanded="false" aria-label="{{ __('landing.toggle_menu') }}">
                <i class="bi bi-list" aria-hidden="true"></i>
            </button>

            <div class="collapse navbar-collapse" id="siteNav">
                <ul class="navbar-nav mx-lg-auto">
                    <li class="nav-item">
                        <a @class(['nav-link', 'active' => request()->routeIs('home')]) href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif>{{ __('landing.nav.home') }}</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a @class(['nav-link', 'dropdown-toggle', 'active' => request()->routeIs($aboutRoutes)]) href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            {{ __('landing.nav.about_group') }}
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('pages.about') }}">{{ __('landing.nav.about') }}</a></li>
                            <li><a class="dropdown-item" href="{{ route('pages.how') }}">{{ __('landing.nav.how') }}</a></li>
                            <li><a class="dropdown-item" href="{{ route('pages.supported') }}">{{ __('landing.nav.supported') }}</a></li>
                        </ul>
                    </li>
                    @foreach ($links as [$route, $label])
                        <li class="nav-item">
                            <a @class(['nav-link', 'active' => request()->routeIs($route)]) href="{{ route($route) }}" @if (request()->routeIs($route)) aria-current="page" @endif>{{ __($label) }}</a>
                        </li>
                    @endforeach
                </ul>

                <div class="site-nav-actions">
                    @include('partials.lang-switch')
                    @auth
                        <a href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : route('dashboard') }}" class="btn btn-site btn-sm">{{ __('landing.nav.dashboard') }}</a>
                    @else
                        <a href="{{ route('login') }}" @class(['btn btn-sm', 'btn-site' => $onLogin, 'btn-site-ghost' => ! $onLogin]) @if ($onLogin) aria-current="page" @endif>{{ __('landing.nav.login') }}</a>
                        <a href="{{ route('register') }}" @class(['btn btn-sm', 'btn-site' => ! $onLogin, 'btn-site-ghost' => $onLogin]) @if ($onRegister) aria-current="page" @endif>{{ __('landing.nav.register') }}</a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>
</header>
