@extends('layouts.panel')

@section('title', __('verifications.progress.title'))

@php
    $steps = collect(['queued', 'sending', 'analyzing', 'saving'])
        ->map(fn ($key) => ['key' => $key, 'label' => __('verifications.stages.'.$key)])->all();

    $trackerConfig = [
        'url' => route('verifications.status', $verification),
        'progress' => (int) $verification->progress,
        'statusLabel' => $verification->status->label(),
        'stageLabel' => $verification->stage ? __('verifications.stages.'.$verification->stage) : '',
        'stage' => $verification->stage,
        'steps' => $steps,
    ];
@endphp

@section('content')
    <div class="page-head">
        <h1>{{ __('verifications.progress.title') }}</h1>
    </div>

    <div class="aimv-card mx-auto" style="max-width:48rem" x-data="progressTracker(@js($trackerConfig))">
        <div class="card-head">
            <div class="d-flex align-items-center gap-2 text-break">
                <i class="bi {{ $verification->mediaFile->media_type->icon() }} fs-4 text-accent" aria-hidden="true"></i>
                <div>
                    <div class="fw-bold">{{ $verification->mediaFile->original_name }}</div>
                    <div class="small text-muted ltr-text">{{ $verification->reference_number }}</div>
                </div>
            </div>
            <span class="verdict-badge verdict-neutral">
                <i class="bi bi-hourglass-split" aria-hidden="true"></i> <span x-text="statusLabel"></span>
            </span>
        </div>

        <div class="card-body-pad">
            <div class="d-flex justify-content-between mb-1">
                <span class="fw-bold" x-text="stageLabel" role="status" aria-live="polite"></span>
                <span class="ltr-text" x-text="progress + '%'"></span>
            </div>
            <div class="progress" style="height:1rem" role="progressbar" :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('verifications.progress.title') }}">
                <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" :style="'width:' + progress + '%'"></div>
            </div>

            <ol class="list-unstyled mt-4 mb-0 d-grid gap-2">
                <template x-for="step in steps" :key="step.key">
                    <li class="d-flex align-items-center gap-2" :class="{ 'fw-bold': stepState(step.key) === 'active', 'text-muted': stepState(step.key) === 'todo' }">
                        <i class="bi" :class="{
                            'bi-check-circle-fill text-success': stepState(step.key) === 'done',
                            'bi-arrow-repeat text-accent': stepState(step.key) === 'active',
                            'bi-circle': stepState(step.key) === 'todo'
                        }" aria-hidden="true"></i>
                        <span x-text="step.label"></span>
                    </li>
                </template>
            </ol>

            <div class="alert alert-info mt-4 mb-0" x-show="hint && !lostConnection" x-cloak role="status" x-text="hint"></div>
            <div class="alert alert-warning mt-4 mb-0" x-show="lostConnection" x-cloak role="alert">
                {{ __('verifications.progress.connection_lost') }}
            </div>
            <div class="notice-box mt-4">{{ __('verifications.progress.leave_notice') }}</div>
        </div>
    </div>

    <noscript>
        <meta http-equiv="refresh" content="5">
    </noscript>
@endsection
