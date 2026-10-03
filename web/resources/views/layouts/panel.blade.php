@extends('layouts.base')

@php
    $authUser = auth()->user();
    $panelType = trim($__env->yieldContent('panel')) ?: ($authUser->isAdmin() ? 'admin' : 'user');
    // لوحة المستخدم تأخذ هوية الواجهة العامة (.site)؛ لوحة المدير تبقى كما هي
    $siteTheme = $panelType === 'user';
@endphp

@section('body')
<div @class(['panel-shell', 'site site-panel' => $siteTheme])>
    <aside class="panel-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar" style="--bs-offcanvas-width: 17rem;" aria-label="{{ __('nav.menu') }}">
        <div class="offcanvas-header d-lg-none">
            @if ($siteTheme)
                <span class="site-brand">
                    <span class="site-brand-mark"><img src="{{ asset('image/photo_2026-09-24_13-06-22.jpg') }}" alt="{{ __('landing.logo_alt') }}" width="179" height="60"></span>
                    <span class="site-brand-name" dir="ltr">{{ __('landing.site_name') }}</span>
                </span>
            @else
                <span class="side-brand mb-0">
                    <span class="brand-mark" aria-hidden="true"><i class="bi bi-shield-check"></i></span>
                    {{ __('app.name') }}
                </span>
            @endif
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="{{ __('common.close') }}"></button>
        </div>
        <div class="offcanvas-body">
            @if ($siteTheme)
                <a href="{{ route('home') }}" class="site-brand side-brand-site d-none d-lg-inline-flex">
                    <span class="site-brand-mark"><img src="{{ asset('image/photo_2026-09-24_13-06-22.jpg') }}" alt="{{ __('landing.logo_alt') }}" width="179" height="60"></span>
                    <span class="site-brand-name" dir="ltr">{{ __('landing.site_name') }}</span>
                </a>
            @else
                <a href="{{ route('home') }}" class="side-brand d-none d-lg-flex text-decoration-none">
                    <span class="brand-mark" aria-hidden="true"><i class="bi bi-shield-check"></i></span>
                    {{ __('app.name') }}
                </a>
            @endif
            @include('partials.nav-'.$panelType)
        </div>
    </aside>

    <div class="panel-main">
        <header class="panel-topbar no-print">
            <button type="button" @class(['btn d-lg-none', 'btn-site-ghost' => $siteTheme, 'btn-outline-primary' => ! $siteTheme]) data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="{{ __('nav.menu') }}">
                <i class="bi bi-list" aria-hidden="true"></i>
            </button>

            <div class="d-flex align-items-center gap-2 ms-auto">
                @stack('topbar')
                @include('partials.notifications-bell')
                @include('partials.lang-switch')

                <div class="dropdown">
                    <button class="btn btn-outline-primary btn-sm dropdown-toggle d-flex align-items-center gap-2 panel-user-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="panel-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($authUser->name, 0, 1)) }}</span>
                        <span class="d-none d-sm-inline">{{ \Illuminate\Support\Str::limit($authUser->name, 22) }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="dropdown-item-text small text-muted">{{ $authUser->email }}</li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2" aria-hidden="true"></i>{{ __('nav.profile') }}</a></li>
                        <li><a class="dropdown-item" href="{{ route('settings.edit') }}"><i class="bi bi-gear me-2" aria-hidden="true"></i>{{ __('nav.settings') }}</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>{{ __('auth.logout') }}</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <main id="main" class="panel-content">
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</div>
@endsection
