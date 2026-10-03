@extends('layouts.base')

{{-- هيكل الواجهة العامة: الرئيسية وصفحتا التقدم والنتيجة للزائر --}}
@push('head')
    @if (! empty($noindex))
        <meta name="robots" content="noindex, nofollow">
    @endif
@endpush

@section('body')
<div class="site">
    @include('partials.site-header')

    <main id="main">
        @if (session('success') || session('status') || session('error'))
            <div class="container pt-3">@include('partials.flash')</div>
        @endif
        @yield('content')
    </main>

    @include('partials.site-footer')
</div>
@endsection
