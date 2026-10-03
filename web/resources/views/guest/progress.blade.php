@extends('layouts.site', ['noindex' => true])

@section('title', __('verifications.progress.title'))

@php
    $steps = collect(['queued', 'sending', 'analyzing', 'saving'])
        ->map(fn ($key) => ['key' => $key, 'label' => __('verifications.stages.'.$key)])->all();

    $trackerConfig = [
        'url' => route('guest.status', $verification),
        'progress' => (int) $verification->progress,
        'statusLabel' => $verification->status->label(),
        'stageLabel' => $verification->stage ? __('verifications.stages.'.$verification->stage) : '',
        'stage' => $verification->stage,
        'steps' => $steps,
    ];
    $media = $verification->mediaFile;
@endphp

@section('content')
<section class="site-section">
    <div class="container case-narrow" x-data="progressTracker(@js($trackerConfig))">
        <header class="case-head">
            <p class="case-ref"><span>{{ __('landing.case.label') }}</span> <span class="ltr-text">{{ $verification->reference_number }}</span></p>
            <h1 class="case-title">{{ __('verifications.progress.title') }}</h1>
            <p class="site-lead mb-0">{{ __('landing.case.progress_lead') }}</p>
        </header>

        <div class="case-sheet">
            <div class="case-file">
                <i class="bi {{ $media->media_type->icon() }}" aria-hidden="true"></i>
                <div class="min-w-0">
                    <div class="picked-name text-break">{{ $media->original_name }}</div>
                    <div class="picked-size"><span>{{ $media->media_type->label() }}</span> · <span class="ltr-text">{{ $media->humanSize() }}</span></div>
                </div>
            </div>

            {{-- حالة التحميل --}}
            <div class="case-progress" role="status" aria-live="polite">
                <div class="d-flex justify-content-between align-items-baseline gap-2 mb-2">
                    <span class="case-progress-stage">
                        <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span><span x-text="stageLabel || statusLabel"></span>
                    </span>
                    <span class="case-progress-num ltr-text" x-text="progress + '%'"></span>
                </div>
                <div class="site-progress is-lg" role="progressbar" :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('verifications.progress.title') }}">
                    <span :style="'width:' + progress + '%'"></span>
                </div>
            </div>

            <ol class="stages">
                <template x-for="step in steps" :key="step.key">
                    <li class="stage" :class="'is-' + stepState(step.key)">
                        <span class="stage-mark" aria-hidden="true">
                            <i class="bi bi-check-lg" x-show="stepState(step.key) === 'done'"></i>
                        </span>
                        <span x-text="step.label"></span>
                    </li>
                </template>
            </ol>

            {{-- حالة انقطاع الاتصال: تستمر المحاولة تلقائيًا --}}
            <div class="site-alert is-warn mt-3" x-show="lostConnection" x-cloak role="alert">
                <i class="bi bi-wifi-off" aria-hidden="true"></i>
                <span>{{ __('verifications.progress.connection_lost') }}</span>
            </div>
        </div>

        <p class="site-note mt-4 mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i><span>{{ __('home_upload.guest_notice') }}</span></p>
    </div>
</section>

<noscript>
    <meta http-equiv="refresh" content="5">
</noscript>
@endsection
