@extends('layouts.base')

@php
    // هل جاء المستخدم من زر «طباعة التقرير» كزائر؟ (الوجهة المقصودة تشير إلى مسار استلام النتيجة)
    $resumingResult = str_contains((string) session('url.intended'), '/guest/verifications/');
@endphp

@section('body')
{{-- صفحات الدخول والتسجيل: الهوية نفسها للواجهة الرئيسية (الشريط والألوان والخط)، ونموذج واحد في المنتصف --}}
<div class="site site-auth">
    @include('partials.site-header')

    <main id="main" class="auth-main">
        <div @class(['auth-card', 'is-wide' => request()->routeIs('register')])>
            @if ($resumingResult)
                <div class="site-alert is-info mb-4" role="status">
                    <i class="bi bi-file-earmark-check" aria-hidden="true"></i>
                    <div>
                        <strong class="d-block">{{ __('landing.auth.resume_title') }}</strong>
                        {{ __('landing.auth.resume_text') }}
                    </div>
                </div>
            @endif

            {{-- أخطاء الحقول تظهر تحت كل حقل، فلا حاجة لملخص مكرر يطيل النموذج --}}
            @include('partials.flash', ['hideErrorSummary' => true])

            @yield('content')
        </div>
    </main>
</div>
@endsection
