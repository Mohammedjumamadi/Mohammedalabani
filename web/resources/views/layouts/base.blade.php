<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ config('aimv.locales.'.app()->getLocale().'.dir', 'rtl') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('app.name')) | {{ __('app.name') }}</title>
    <meta name="description" content="@yield('description', __('app.tagline'))">
    @vite([app()->getLocale() === 'ar' ? 'resources/css/app-rtl.css' : 'resources/css/app-ltr.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body>
    <a class="skip-link" href="#main">{{ __('app.skip_to_content') }}</a>
    @yield('body')
    @stack('scripts')
</body>
</html>
