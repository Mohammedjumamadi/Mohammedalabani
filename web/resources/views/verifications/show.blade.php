@extends('layouts.panel')

@section('title', __('verifications.show.title'))

@php
    use App\Enums\MediaType;
    use App\Enums\RequestStatus;

    $isOwner = auth()->id() === $verification->user_id;
    $fmtTime = fn (?float $s) => $s === null ? null : sprintf('%02d:%02d', intdiv((int) $s, 60), (int) $s % 60);
    $meta = $media->metadata ?? [];
@endphp

@section('content')
    {{-- Header Dossier Toolbar --}}
    <div class="dash-page-head">
        <div>
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                <span class="report-badge-pill">
                    <i class="bi bi-shield-check" aria-hidden="true"></i> {{ __('verifications.show.title') }}
                </span>
                <span class="badge bg-secondary-subtle text-secondary border ltr-text font-monospace small">
                    #{{ $verification->reference_number }}
                </span>
            </div>
            <h1 class="dash-welcome fs-3 text-break">{{ $media->original_name }}</h1>
        </div>
        <div class="d-flex gap-2 flex-wrap no-print">
            @if ($verification->status->hasResult())
                <a href="{{ route('reports.pdf', $verification) }}" class="btn btn-primary d-inline-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                    <span>{{ __('verifications.show.download_pdf') }}</span>
                </a>
                <a href="{{ route('verifications.print', [$verification, 'autoprint' => 1]) }}" class="btn btn-outline-primary d-inline-flex align-items-center gap-2" target="_blank" rel="noopener">
                    <i class="bi bi-printer" aria-hidden="true"></i>
                    <span>{{ __('reports.print.button') }}</span>
                </a>
                @can('share', $verification)
                    <a href="#share" class="btn btn-outline-primary d-inline-flex align-items-center gap-2">
                        <i class="bi bi-share" aria-hidden="true"></i>
                        <span>{{ __('verifications.show.share') }}</span>
                    </a>
                @endcan
            @endif
            <form method="POST" action="{{ route('verifications.destroy', $verification) }}" onsubmit="return confirm(@js(__('common.confirm_delete')))">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-outline-danger d-inline-flex align-items-center gap-2">
                    <i class="bi bi-trash" aria-hidden="true"></i>
                    <span>{{ __('common.delete') }}</span>
                </button>
            </form>
        </div>
    </div>

    @if ($verification->is_demo)
        <div class="demo-banner mb-4 d-flex align-items-center gap-2">
            <i class="bi bi-info-circle-fill fs-5" aria-hidden="true"></i>
            <span>{{ __('common.demo_data_help') }}</span>
        </div>
    @endif

    @if ($verification->reusedFrom)
        <div class="notice-box mb-4 d-flex align-items-center gap-2">
            <i class="bi bi-recycle fs-5 text-accent" aria-hidden="true"></i>
            <span>{{ __('verifications.show.reused', ['date' => $verification->reusedFrom->completed_at?->translatedFormat('j F Y') ?? '—']) }}</span>
        </div>
    @endif

    @if (! $verification->status->hasResult())
        {{-- Status In Progress or Failed --}}
        <div class="aimv-card card-body-pad p-5 text-center">
            @if ($verification->status === RequestStatus::Failed)
                <div class="verdict-panel verdict-fake mb-4 text-start">
                    <div class="d-flex gap-3 align-items-start">
                        <i class="bi bi-x-octagon-fill verdict-icon" aria-hidden="true"></i>
                        <div>
                            <h2 class="verdict-title">{{ __('enums.request_status.failed') }}</h2>
                            <p class="verdict-text mb-0">{{ __('ai_errors.'.($verification->error_message ?: 'unexpected')) }}</p>
                        </div>
                    </div>
                </div>
                @can('retry', $verification)
                    <form method="POST" action="{{ route('verifications.retry', $verification) }}">
                        @csrf
                        <button class="btn btn-primary btn-lg">
                            <i class="bi bi-arrow-repeat me-2" aria-hidden="true"></i> {{ __('verifications.show.retry') }}
                        </button>
                    </form>
                @endcan
            @else
                <div class="py-4">
                    <div class="spinner-border text-accent mb-3" style="width: 3rem; height: 3rem;" role="status"></div>
                    <h2 class="h4 text-primary">{{ __('verifications.show.in_progress') }}</h2>
                    <p class="text-muted mb-4">{{ __('home_upload.guest_notice') }}</p>
                    <a href="{{ route('verifications.progress', $verification) }}" class="btn btn-primary">
                        <i class="bi bi-arrow-right me-1" aria-hidden="true"></i> {{ __('verifications.show.follow_progress') }}
                    </a>
                </div>
            @endif
        </div>
    @else
        @php
            $classification = $result->classification;
            $indicators = $result->indicators
                ->sortBy(fn ($i) => [['high' => 0, 'medium' => 1, 'low' => 2, 'info' => 3][$i->severity->value] ?? 4, -($i->score ?? 0)])
                ->values();
            $frames = $indicators->filter(fn ($i) => $i->frame_path)->unique('frame_path')->values();
            $timeline = $indicators->filter(fn ($i) => $i->timestamp_start !== null)->sortBy('timestamp_start')->values();
            $explanation = $result->displayExplanation($media->media_type);
        @endphp

        {{-- Executive Classification Banner --}}
        <section class="verdict-panel {{ $classification->cssClass() }} mb-4 shadow-sm" aria-labelledby="verdict-title">
            <div class="d-flex gap-3 align-items-start flex-wrap flex-sm-nowrap">
                <div class="verdict-icon-container">
                    <i class="bi {{ $classification->icon() }} verdict-icon" aria-hidden="true"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="text-uppercase small fw-bold tracking-wider opacity-75">{{ __('verifications.show.final_classification') }}</div>
                    <h2 id="verdict-title" class="verdict-title fw-bold my-1">{{ $classification->label() }}</h2>
                    <p class="verdict-text mt-2 mb-0 lead fs-6">{{ $explanation }}</p>
                </div>
                <div class="text-center flex-shrink-0 bg-white bg-opacity-25 p-3 rounded-3 border border-white border-opacity-25">
                    <div class="small fw-bold mb-1">{{ __('verifications.show.risk_level') }}</div>
                    <x-verdict-badge :value="$result->risk_level" />
                </div>
            </div>
        </section>

        {{-- Probability & Confidence Meters --}}
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="aimv-card card-body-pad h-100 p-4 d-flex flex-column justify-content-center text-center">
                    <div class="text-muted small fw-bold mb-1">{{ __('verifications.show.confidence') }}</div>
                    <div class="stat-value display-5 fw-extrabold text-primary ltr-text my-2">
                        {{ number_format($result->confidence_score, 1) }}<span class="fs-4 fw-normal text-muted">%</span>
                    </div>
                    <div class="form-text text-muted small">{{ __('verifications.show.confidence_help') }}</div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="aimv-card card-body-pad h-100 p-4 d-grid gap-3 align-content-center">
                    <x-prob-meter :value="$result->authenticity_probability" :label="__('verifications.show.authenticity')" tone="real" />
                    <x-prob-meter :value="$result->manipulation_probability" :label="__('verifications.show.manipulation')" tone="fake" />
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                {{-- Forensic Indicators --}}
                <section class="aimv-card mb-4" aria-labelledby="indicators-title">
                    <div class="card-head py-3 px-4 bg-surface border-bottom d-flex align-items-center justify-content-between">
                        <h2 id="indicators-title" class="h6 mb-0 text-primary d-flex align-items-center gap-2">
                            <i class="bi bi-fingerprint text-accent" aria-hidden="true"></i>
                            <span>{{ __('verifications.show.indicators') }}</span>
                        </h2>
                        <span class="badge bg-primary-subtle text-primary rounded-pill px-2.5 py-1">{{ $indicators->count() }}</span>
                    </div>
                    @forelse ($indicators as $indicator)
                        <article class="p-4 {{ $loop->last ? '' : 'border-bottom' }} hover-bg-subtle transition-all">
                            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-2">
                                <h3 class="h6 fw-bold mb-0 text-primary">{{ $indicator->title() }}</h3>
                                <div class="d-flex gap-1.5 flex-wrap align-items-center">
                                    <span class="badge bg-light text-muted border">
                                        {{ $indicator->category === 'model' ? __('indicators.from_model') : __('indicators.supporting') }}
                                    </span>
                                    @if (! empty($indicator->details['experimental']))
                                        <span class="badge bg-warning-subtle text-warning border border-warning">{{ __('indicators.experimental') }}</span>
                                    @endif
                                    <x-verdict-badge :value="$indicator->severity" />
                                </div>
                            </div>
                            <p class="text-muted small mb-3">{{ $indicator->explanation() }}</p>
                            @if ($indicator->score !== null)
                                <div class="mb-2">
                                    <x-prob-meter :value="$indicator->score" :label="__('verifications.show.indicator_score')" tone="accent" />
                                </div>
                            @endif
                            @if ($indicator->description && $indicator->description !== $indicator->explanation())
                                <div class="p-2.5 bg-surface rounded-2 border small text-muted mt-2">{{ $indicator->description }}</div>
                            @endif
                            @if ($indicator->timestamp_start !== null)
                                <div class="small mt-2 text-muted d-flex align-items-center gap-1.5">
                                    <i class="bi bi-clock-history text-accent" aria-hidden="true"></i>
                                    <span>{{ __('verifications.show.at_time') }}</span>
                                    <span class="ltr-text fw-bold text-dark">{{ $fmtTime($indicator->timestamp_start) }}@if ($indicator->timestamp_end !== null && $indicator->timestamp_end != $indicator->timestamp_start) – {{ $fmtTime($indicator->timestamp_end) }}@endif</span>
                                </div>
                            @endif
                        </article>
                    @empty
                        <div class="p-4 text-center text-muted">{{ __('verifications.show.no_indicators') }}</div>
                    @endforelse
                </section>

                {{-- Suspicious Video Frames --}}
                @if ($frames->isNotEmpty())
                    <section class="aimv-card mb-4" aria-labelledby="frames-title">
                        <div class="card-head py-3 px-4 bg-surface border-bottom">
                            <h2 id="frames-title" class="h6 mb-0 text-primary d-flex align-items-center gap-2">
                                <i class="bi bi-film text-accent" aria-hidden="true"></i>
                                <span>{{ __('verifications.show.suspicious_frames') }}</span>
                            </h2>
                        </div>
                        <div class="card-body-pad p-4">
                            <div class="row g-3">
                                @foreach ($frames as $frame)
                                    <div class="col-6 col-md-4">
                                        <div class="frame-card rounded-3 overflow-hidden border shadow-sm position-relative">
                                            <a href="{{ route('verifications.frame', [$verification, $frame]) }}" target="_blank" rel="noopener" class="d-block">
                                                <img class="frame-thumb w-100" loading="lazy" src="{{ route('verifications.frame', [$verification, $frame]) }}"
                                                     alt="{{ __('verifications.show.frame_alt', ['time' => $fmtTime($frame->timestamp_start) ?? '']) }}">
                                            </a>
                                            <div class="p-1.5 bg-dark bg-opacity-75 text-white small text-center ltr-text font-monospace">
                                                <i class="bi bi-stopwatch me-1"></i>{{ $fmtTime($frame->timestamp_start) }}
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif

                {{-- Timeline --}}
                @if ($timeline->isNotEmpty() && $media->media_type !== MediaType::Image)
                    <section class="aimv-card mb-4" aria-labelledby="timeline-title">
                        <div class="card-head py-3 px-4 bg-surface border-bottom">
                            <h2 id="timeline-title" class="h6 mb-0 text-primary d-flex align-items-center gap-2">
                                <i class="bi bi-activity text-accent" aria-hidden="true"></i>
                                <span>{{ __('verifications.show.timeline') }}</span>
                            </h2>
                        </div>
                        <ol class="list-group list-group-flush mb-0">
                            @foreach ($timeline as $item)
                                <li class="list-group-item d-flex justify-content-between align-items-center gap-2 py-3 px-4">
                                    <span class="d-flex align-items-center gap-2">
                                        <i class="bi bi-flag-fill text-danger" aria-hidden="true"></i>
                                        <span class="fw-semibold text-dark">{{ $item->title() }}</span>
                                    </span>
                                    <span class="badge bg-light text-dark border ltr-text font-monospace">{{ $fmtTime($item->timestamp_start) }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @endif

                {{-- Recommendations --}}
                <section class="aimv-card mb-4" aria-labelledby="actions-title">
                    <div class="card-head py-3 px-4 bg-surface border-bottom">
                        <h2 id="actions-title" class="h6 mb-0 text-primary d-flex align-items-center gap-2">
                            <i class="bi bi-shield-check text-success" aria-hidden="true"></i>
                            <span>{{ __('verifications.show.recommendations') }}</span>
                        </h2>
                    </div>
                    <ul class="card-body-pad p-4 mb-0 list-unstyled d-grid gap-2.5">
                        @foreach (__('verifications.recommendations.'.$classification->value) as $tip)
                            <li class="d-flex align-items-start gap-2.5">
                                <i class="bi bi-arrow-left-circle-fill text-accent flex-shrink-0 mt-1" aria-hidden="true"></i>
                                <span>{{ $tip }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>

                {{-- Share Box --}}
                @can('share', $verification)
                    <section id="share" class="aimv-card mb-4 no-print" aria-labelledby="share-title">
                        <div class="card-head py-3 px-4 bg-surface border-bottom">
                            <h2 id="share-title" class="h6 mb-0 text-primary d-flex align-items-center gap-2">
                                <i class="bi bi-share text-accent" aria-hidden="true"></i>
                                <span>{{ __('verifications.share.title') }}</span>
                            </h2>
                        </div>
                        <div class="card-body-pad p-4">
                            @if ($shared)
                                <label class="form-label fw-bold" for="share-url">{{ __('verifications.share.link') }}</label>
                                <div class="input-group mb-3" x-data="copyText(@js($shared->publicUrl()))">
                                    <input id="share-url" type="text" class="form-control" dir="ltr" readonly value="{{ $shared->publicUrl() }}" onfocus="this.select()">
                                    <button type="button" class="btn btn-primary" @click="copy()">
                                        <i class="bi" :class="done ? 'bi-check2' : 'bi-clipboard'" aria-hidden="true"></i>
                                        <span x-text="done ? @js(__('common.copied')) : @js(__('common.copy'))"></span>
                                    </button>
                                </div>
                                <div class="p-3 bg-surface rounded-3 border small text-muted mb-3 d-flex flex-wrap gap-2">
                                    <span>{{ $shared->hide_owner ? __('verifications.share.owner_hidden') : __('verifications.share.owner_visible') }}</span>
                                    <span>·</span>
                                    <span>{{ $shared->show_preview ? __('verifications.share.preview_on') : __('verifications.share.preview_off') }}</span>
                                    <span>·</span>
                                    <span>{{ __('verifications.share.views', ['count' => $shared->views_count]) }}</span>
                                    @if ($shared->expires_at)
                                        <span>·</span>
                                        <span>{{ __('verifications.share.expires', ['date' => $shared->expires_at->translatedFormat('j F Y')]) }}</span>
                                    @endif
                                </div>
                                <form method="POST" action="{{ route('verifications.share.destroy', $verification) }}" onsubmit="return confirm(@js(__('verifications.share.confirm_revoke')))">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-outline-danger">
                                        <i class="bi bi-slash-circle me-1" aria-hidden="true"></i> {{ __('verifications.share.revoke') }}
                                    </button>
                                </form>
                            @else
                                <p class="text-muted small mb-3">{{ __('verifications.share.help') }}</p>
                                <form method="POST" action="{{ route('verifications.share.store', $verification) }}">
                                    @csrf
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="hide_owner" id="hide_owner" value="1" checked>
                                        <label class="form-check-label" for="hide_owner">{{ __('verifications.share.hide_owner') }}</label>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="show_preview" id="show_preview" value="1">
                                        <label class="form-check-label" for="show_preview">{{ __('verifications.share.show_preview') }}</label>
                                        <div class="form-text small">{{ __('verifications.share.show_preview_help') }}</div>
                                    </div>
                                    <div class="mb-3" style="max-width:18rem">
                                        <label for="expires_in" class="form-label small fw-semibold">{{ __('verifications.share.expiry') }}</label>
                                        <select id="expires_in" name="expires_in" class="form-select">
                                            <option value="">{{ __('verifications.share.never') }}</option>
                                            <option value="7">{{ __('verifications.share.days', ['count' => 7]) }}</option>
                                            <option value="30">{{ __('verifications.share.days', ['count' => 30]) }}</option>
                                            <option value="90">{{ __('verifications.share.days', ['count' => 90]) }}</option>
                                        </select>
                                    </div>
                                    <button class="btn btn-accent px-3 py-2">
                                        <i class="bi bi-link-45deg me-1" aria-hidden="true"></i> {{ __('verifications.share.create') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </section>
                @endcan
            </div>

            <div class="col-lg-4">
                {{-- Media Preview Card --}}
                <section class="aimv-card mb-4" aria-labelledby="preview-title">
                    <div class="card-head py-3 px-4 bg-surface border-bottom">
                        <h2 id="preview-title" class="h6 mb-0 text-primary d-flex align-items-center gap-2">
                            <i class="bi bi-eye text-accent" aria-hidden="true"></i>
                            <span>{{ __('verifications.show.preview') }}</span>
                        </h2>
                    </div>
                    <div class="card-body-pad p-3">
                        @if ($media->existsOnDisk())
                            <div class="preview-frame rounded-3 overflow-hidden shadow-sm">
                                @if ($media->media_type === MediaType::Image)
                                    <img src="{{ route('verifications.media', $verification) }}" alt="{{ __('verifications.show.preview_alt') }}" class="w-100 d-block">
                                @elseif ($media->media_type === MediaType::Video)
                                    <video controls preload="metadata" src="{{ route('verifications.media', $verification) }}" class="w-100 d-block"></video>
                                @else
                                    <div class="p-3 bg-dark"><audio controls preload="metadata" src="{{ route('verifications.media', $verification) }}" class="w-100"></audio></div>
                                @endif
                            </div>
                        @else
                            <div class="text-center text-muted py-4">
                                <i class="bi {{ $media->media_type->icon() }} fs-1 d-block mb-2" aria-hidden="true"></i>
                                <p class="mb-0 small">{{ __('verifications.show.file_unavailable') }}</p>
                            </div>
                        @endif
                    </div>
                </section>

                {{-- File Specs --}}
                <section class="aimv-card mb-4" aria-labelledby="file-title">
                    <div class="card-head py-3 px-4 bg-surface border-bottom">
                        <h2 id="file-title" class="h6 mb-0 text-primary d-flex align-items-center gap-2">
                            <i class="bi bi-file-earmark-text text-accent" aria-hidden="true"></i>
                            <span>{{ __('verifications.show.file_info') }}</span>
                        </h2>
                    </div>
                    <dl class="card-body-pad p-4 mb-0 small specs-definition-list">
                        <div class="spec-row">
                            <dt class="text-muted">{{ __('verifications.show.file_name') }}</dt>
                            <dd class="text-break fw-semibold mb-0">{{ $media->original_name }}</dd>
                        </div>
                        <div class="spec-row">
                            <dt class="text-muted">{{ __('verifications.show.file_type') }}</dt>
                            <dd class="mb-0">{{ $media->media_type->label() }} <span class="ltr-text text-muted">({{ $media->mime_type }})</span></dd>
                        </div>
                        <div class="spec-row">
                            <dt class="text-muted">{{ __('verifications.show.file_size') }}</dt>
                            <dd class="ltr-text fw-semibold mb-0">{{ $media->humanSize() }}</dd>
                        </div>
                        @if ($media->width && $media->height)
                            <div class="spec-row">
                                <dt class="text-muted">{{ __('verifications.show.dimensions') }}</dt>
                                <dd class="ltr-text mb-0">{{ $media->width }} × {{ $media->height }}</dd>
                            </div>
                        @endif
                        @if ($media->duration_seconds)
                            <div class="spec-row">
                                <dt class="text-muted">{{ __('verifications.show.duration') }}</dt>
                                <dd class="ltr-text mb-0">{{ $fmtTime($media->duration_seconds) }}</dd>
                            </div>
                        @endif
                        @foreach (['fps' => 'fps', 'video_codec' => 'video_codec', 'audio_codec' => 'audio_codec', 'sample_rate' => 'sample_rate'] as $key => $labelKey)
                            @if (! empty($meta[$key]))
                                <div class="spec-row">
                                    <dt class="text-muted">{{ __('verifications.show.meta.'.$labelKey) }}</dt>
                                    <dd class="ltr-text mb-0">{{ $meta[$key] }}</dd>
                                </div>
                            @endif
                        @endforeach
                        <div class="spec-row">
                            <dt class="text-muted">{{ __('verifications.show.uploaded_at') }}</dt>
                            <dd class="mb-0">{{ $media->created_at->translatedFormat('j F Y - H:i') }}</dd>
                        </div>
                        <div class="spec-row">
                            <dt class="text-muted">{{ __('verifications.show.status') }}</dt>
                            <dd class="mb-0"><x-verdict-badge :value="$verification->status" /></dd>
                        </div>
                        <div class="spec-row pt-2">
                            <dt class="text-muted mb-1">{{ __('verifications.show.sha256') }}</dt>
                            <dd class="hash p-2 bg-surface rounded-2 border mb-0 text-break font-monospace small">{{ $media->sha256 }}</dd>
                        </div>
                    </dl>
                </section>

                {{-- AI Model Specs --}}
                <section class="aimv-card mb-4" aria-labelledby="model-title">
                    <div class="card-head py-3 px-4 bg-surface border-bottom">
                        <h2 id="model-title" class="h6 mb-0 text-primary d-flex align-items-center gap-2">
                            <i class="bi bi-cpu text-accent" aria-hidden="true"></i>
                            <span>{{ __('verifications.show.model_info') }}</span>
                        </h2>
                    </div>
                    <dl class="card-body-pad p-4 mb-0 small specs-definition-list">
                        <div class="spec-row">
                            <dt class="text-muted">{{ __('verifications.show.model_name') }}</dt>
                            <dd class="text-break ltr-text fw-bold text-primary mb-0">{{ $result->model_name }}</dd>
                        </div>
                        <div class="spec-row">
                            <dt class="text-muted">{{ __('verifications.show.model_version') }}</dt>
                            <dd class="ltr-text mb-0"><span class="badge bg-secondary-subtle text-secondary border">v{{ $result->model_version }}</span></dd>
                        </div>
                        @if ($result->modelVersion?->source_url)
                            <div class="spec-row">
                                <dt class="text-muted">{{ __('verifications.show.model_source') }}</dt>
                                <dd class="text-break mb-0"><a href="{{ $result->modelVersion->source_url }}" class="ltr-text text-accent" target="_blank" rel="noopener noreferrer">{{ $result->modelVersion->source_url }}</a></dd>
                            </div>
                        @endif
                        <div class="spec-row">
                            <dt class="text-muted">{{ __('verifications.show.processing_time') }}</dt>
                            <dd class="mb-0 fw-semibold">{{ __('verifications.show.seconds', ['value' => number_format($result->processing_time ?? 0, 1)]) }}</dd>
                        </div>
                        <div class="spec-row">
                            <dt class="text-muted">{{ __('verifications.show.completed_at') }}</dt>
                            <dd class="mb-0">{{ $result->completed_at?->translatedFormat('j F Y - H:i') }}</dd>
                        </div>
                        @if ($result->thresholds)
                            <div class="spec-row">
                                <dt class="text-muted">{{ __('verifications.show.thresholds') }}</dt>
                                <dd class="mb-0">{{ __('verifications.show.thresholds_value', ['fake' => $result->thresholds['fake'], 'real' => $result->thresholds['real']]) }}</dd>
                            </div>
                        @endif
                    </dl>
                </section>
            </div>
        </div>

        {{-- Limits & Integrity Notice --}}
        <section class="notice-box is-warning rounded-3 shadow-sm p-4 mt-2" aria-labelledby="limits-title">
            <h2 id="limits-title" class="h6 mb-2 d-flex align-items-center gap-2 text-warning-emphasis">
                <i class="bi bi-exclamation-triangle-fill fs-5" aria-hidden="true"></i>
                <span>{{ __('verifications.disclaimer_title') }}</span>
            </h2>
            <p class="mb-3 small text-muted">{{ __('verifications.disclaimer_long') }}</p>
            <ul class="mb-0 ps-4 small text-muted d-grid gap-1.5">
                @foreach (__('verifications.limits') as $limit)
                    <li>{{ $limit }}</li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
