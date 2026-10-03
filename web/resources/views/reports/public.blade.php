@extends('layouts.public')

@section('title', __('reports.public.title'))

@php
    use App\Enums\MediaType;
    $classification = $result->classification;
    $fmtTime = fn (?float $s) => $s === null ? null : sprintf('%02d:%02d', intdiv((int) $s, 60), (int) $s % 60);
    $indicators = $result->indicators->sortByDesc('score')->values();
@endphp

@section('content')
<div class="container section" style="max-width:58rem">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h2">{{ __('reports.public.title') }}</h1>
            <div class="text-muted small">{{ __('verifications.show.reference') }}: <span class="ltr-text fw-bold">{{ $verification->reference_number }}</span></div>
        </div>
        <button type="button" class="btn btn-outline-primary no-print" onclick="window.print()"><i class="bi bi-printer" aria-hidden="true"></i> {{ __('common.print') }}</button>
    </div>

    @if ($verification->is_demo)
        <div class="demo-banner mb-3">{{ __('common.demo_data_help') }}</div>
    @endif

    <section class="verdict-panel {{ $classification->cssClass() }} mb-4" aria-labelledby="pv">
        <div class="d-flex gap-3">
            <i class="bi {{ $classification->icon() }} verdict-icon" aria-hidden="true"></i>
            <div>
                <div class="small fw-bold">{{ __('verifications.show.final_classification') }}</div>
                <h2 id="pv" class="verdict-title">{{ $classification->label() }}</h2>
                <p class="verdict-text mt-2 mb-0">{{ $result->displayExplanation($media->media_type) }}</p>
            </div>
        </div>
    </section>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="aimv-card card-body-pad h-100">
            <div class="text-muted small">{{ __('verifications.show.confidence') }}</div>
            <div class="fs-2 fw-bold text-primary-dark ltr-text">{{ number_format($result->confidence_score, 1) }}%</div>
            <div class="form-text">{{ __('verifications.show.confidence_help') }}</div>
        </div></div>
        <div class="col-md-8"><div class="aimv-card card-body-pad h-100 d-grid gap-3">
            <x-prob-meter :value="$result->authenticity_probability" :label="__('verifications.show.authenticity')" tone="real" />
            <x-prob-meter :value="$result->manipulation_probability" :label="__('verifications.show.manipulation')" tone="fake" />
        </div></div>
    </div>

    @if ($shared->show_preview && $media->existsOnDisk())
        <section class="aimv-card mb-4" aria-labelledby="pp">
            <div class="card-head"><h2 id="pp">{{ __('verifications.show.preview') }}</h2></div>
            <div class="card-body-pad"><div class="preview-frame">
                @if ($media->media_type === MediaType::Image)
                    <img src="{{ route('reports.public.media', $token) }}" alt="{{ __('verifications.show.preview_alt') }}">
                @elseif ($media->media_type === MediaType::Video)
                    <video controls preload="metadata" src="{{ route('reports.public.media', $token) }}"></video>
                @else
                    <div class="p-3"><audio controls src="{{ route('reports.public.media', $token) }}"></audio></div>
                @endif
            </div></div>
        </section>
    @endif

    <section class="aimv-card mb-4" aria-labelledby="pi">
        <div class="card-head"><h2 id="pi">{{ __('verifications.show.indicators') }}</h2></div>
        @forelse ($indicators as $indicator)
            <article class="card-body-pad {{ $loop->last ? '' : 'border-bottom' }}">
                <div class="d-flex justify-content-between gap-2 flex-wrap">
                    <h3 class="h6 mb-1">{{ $indicator->title() }}</h3>
                    <x-verdict-badge :value="$indicator->severity" />
                </div>
                <p class="text-muted small mb-0">{{ $indicator->explanation() }}
                    @if ($indicator->timestamp_start !== null) — <span class="ltr-text fw-bold">{{ $fmtTime($indicator->timestamp_start) }}</span>@endif
                </p>
            </article>
        @empty
            <div class="card-body-pad text-muted">{{ __('verifications.show.no_indicators') }}</div>
        @endforelse
    </section>

    <section class="aimv-card mb-4" aria-labelledby="pf">
        <div class="card-head"><h2 id="pf">{{ __('verifications.show.file_info') }}</h2></div>
        <dl class="card-body-pad mb-0 small">
            <dt class="text-muted">{{ __('verifications.show.file_type') }}</dt><dd>{{ $media->media_type->label() }}</dd>
            <dt class="text-muted">{{ __('verifications.show.file_size') }}</dt><dd class="ltr-text">{{ $media->humanSize() }}</dd>
            <dt class="text-muted">{{ __('verifications.show.model_name') }}</dt><dd class="ltr-text">{{ $result->model_name }} <span class="text-muted">v{{ $result->model_version }}</span></dd>
            <dt class="text-muted">{{ __('verifications.show.completed_at') }}</dt><dd>{{ $result->completed_at?->translatedFormat('j F Y - H:i') }}</dd>
            <dt class="text-muted">{{ __('verifications.show.sha256') }}</dt><dd class="hash">{{ $media->sha256 }}</dd>
            @unless ($shared->hide_owner)
                <dt class="text-muted">{{ __('reports.public.shared_by') }}</dt><dd class="mb-0">{{ $verification->user->name }}</dd>
            @endunless
        </dl>
    </section>

    <section class="notice-box is-warning" aria-labelledby="pl">
        <h2 id="pl" class="h6"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> {{ __('verifications.disclaimer_title') }}</h2>
        <p class="mb-2">{{ __('verifications.disclaimer_long') }}</p>
        <ul class="mb-0 ps-4">@foreach (__('verifications.limits') as $limit)<li>{{ $limit }}</li>@endforeach</ul>
    </section>
</div>
@endsection
