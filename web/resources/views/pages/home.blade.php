@extends('layouts.site')

@section('title', __('landing.page_title'))

@php
    // حدود التصنيف الفعلية كما يضبطها المدير (لا أرقام ثابتة في الواجهة)
    $t = app(\App\Services\Verification\ClassificationSettings::class)->thresholds();
    $fmt = fn (float $v) => rtrim(rtrim(number_format($v, 1), '0'), '.');
    $bands = [
        'real' => ['width' => $t['real'], 'range' => __('landing.reading.real_range', ['value' => $fmt($t['real'])])],
        'uncertain' => ['width' => $t['fake'] - $t['real'], 'range' => __('landing.reading.uncertain_range', ['real' => $fmt($t['real']), 'fake' => $fmt($t['fake'])])],
        'fake' => ['width' => 100 - $t['fake'], 'range' => __('landing.reading.fake_range', ['value' => $fmt($t['fake'])])],
    ];
    $mediaChecks = ['image' => 'bi-image', 'video' => 'bi-film', 'audio' => 'bi-soundwave'];
@endphp

@section('content')
    {{-- المقدمة: النص وزر البدء في جهة البداية (اليمين في العربية)، والصورة التوضيحية في الجهة الأخرى --}}
    <section class="hero">
        <div class="container">
            <div class="row align-items-center g-4 g-lg-5">
                <div class="col-lg-6">
                    <h1 class="hero-title">
                        {{ __('landing.hero.title') }}
                        <span class="hero-title-accent">{{ __('landing.hero.highlight') }}</span>
                    </h1>
                    <p class="hero-text">{{ __('landing.hero.text') }}</p>

                    <div class="hero-actions">
                        @auth
                            <a href="{{ route('verifications.create') }}" class="btn btn-site btn-lg">
                                {{ __('landing.hero.cta_new') }}
                            </a>
                        @else
                            <a href="#try-now" class="btn btn-site btn-lg">
                                <i class="bi bi-upload me-2" aria-hidden="true"></i>{{ __('landing.hero.cta_try') }}
                            </a>
                        @endauth
                        <a href="#reading" class="btn btn-site-ghost btn-lg">{{ __('landing.hero.cta_how') }}</a>
                    </div>

                    {{-- الرحلة كلها في سطر واحد: رفع ← انتظار ← قراءة --}}
                    <ol class="hero-flow">
                        @foreach (__('landing.hero.steps') as $i => $step)
                            <li><span class="hero-flow-num">{{ $i + 1 }}</span>{{ $step }}</li>
                        @endforeach
                    </ol>
                </div>

                <div class="col-lg-6">
                    <figure class="hero-figure">
                        <img src="{{ asset('image/photo_2026-09-24_13-10-34.jpg') }}" alt="{{ __('landing.illustration_alt') }}" width="1280" height="720">
                    </figure>
                </div>
            </div>
        </div>
    </section>

    {{-- الرفع الحقيقي للزائر، أو توجيه المستخدم المسجَّل إلى لوحته --}}
    @if (! empty($guestTypes))
        @include('partials.guest-uploader')
    @else
        <section class="site-section site-section-alt" id="try-now">
            <div class="container">
                <div class="site-band">
                    <div>
                        <h2 class="site-h2 mb-1">{{ __('landing.upload.signed_in_title') }}</h2>
                        <p class="site-lead mb-0">{{ __('landing.upload.signed_in_text') }}</p>
                    </div>
                    @auth
                        <a href="{{ route('verifications.create') }}" class="btn btn-site btn-lg">{{ __('landing.hero.cta_new') }}</a>
                    @else
                        <a href="{{ route('register') }}" class="btn btn-site btn-lg">{{ __('landing.nav.register') }}</a>
                    @endauth
                </div>
            </div>
        </section>
    @endif

    {{-- كيف تُقرأ النتيجة: مقياس 0–100 مقسوم بالحدود الفعلية --}}
    <section class="site-section" id="reading" aria-labelledby="reading-title">
        <div class="container">
            <div class="row g-4 g-lg-5">
                <div class="col-lg-4">
                    <h2 id="reading-title" class="site-h2">{{ __('landing.reading.title') }}</h2>
                    <p class="site-lead">{{ __('landing.reading.text') }}</p>
                    <p class="site-note mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i><span>{{ __('landing.reading.confidence') }}</span></p>
                </div>
                <div class="col-lg-8">
                    <div class="scale" aria-hidden="true">
                        @foreach ($bands as $key => $band)
                            <span class="scale-band is-{{ $key }}" style="flex-basis: {{ max(0, $band['width']) }}%"></span>
                        @endforeach
                    </div>
                    <div class="scale-axis" aria-hidden="true"><span>0</span><span>100</span></div>

                    <dl class="bands">
                        @foreach ($bands as $key => $band)
                            <div class="band is-{{ $key }}">
                                <dt>
                                    <span class="band-dot" aria-hidden="true"></span>
                                    {{ __('enums.classification.'.$key) }}
                                    <span class="band-range">{{ $band['range'] }}</span>
                                </dt>
                                <dd>{{ __('landing.reading.'.$key.'_text') }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </div>
        </div>
    </section>

    {{-- ما الذي يفحصه النظام: قوائم مأخوذة من المؤشرات الفعلية لخدمة التحليل --}}
    <section class="site-section site-section-alt" id="features" aria-labelledby="checks-title">
        <div class="container">
            <header class="site-section-head">
                <h2 id="checks-title" class="site-h2">{{ __('landing.checks.title') }}</h2>
                <p class="site-lead mb-0">{{ __('landing.checks.text') }}</p>
            </header>

            <div class="checks">
                @foreach ($mediaChecks as $type => $icon)
                    <div class="checks-col">
                        <h3 class="checks-title"><i class="bi {{ $icon }}" aria-hidden="true"></i> {{ __('landing.checks.'.$type.'_title') }}</h3>
                        <ul class="checks-list">
                            @foreach (__('landing.checks.'.$type.'_items') as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    @guest
        <section class="site-section">
            <div class="container">
                <div class="site-band">
                    <div>
                        <h2 class="site-h2 mb-1">{{ __('landing.closing.title') }}</h2>
                        <p class="site-lead mb-0">{{ __('landing.closing.text') }}</p>
                    </div>
                    <a href="{{ route('register') }}" class="btn btn-site btn-lg">{{ __('landing.closing.button') }}</a>
                </div>
            </div>
        </section>
    @endguest
@endsection
