@extends('layouts.site', ['noindex' => true])

@section('title', __('verifications.show.title'))

@php
    use App\Enums\MediaType;
    use App\Enums\RequestStatus;

    $fmtTime = fn (?float $s) => $s === null ? null : sprintf('%02d:%02d', intdiv((int) $s, 60), (int) $s % 60);
    $hasResult = $verification->status->hasResult() && $result;
@endphp

@section('content')
<section class="site-section case-page">
    <div class="container">
        {{-- رأس ملف التحقق --}}
        <header class="case-head case-head-row">
            <div class="min-w-0">
                <p class="case-ref"><span>{{ __('verifications.show.title') }}</span> <span class="ltr-text">{{ $verification->reference_number }}</span></p>
                <h1 class="case-title text-break">{{ $media->original_name }}</h1>
            </div>
            @if ($hasResult)
                <div class="case-actions no-print">
                    {{-- الرابط يعمل دون JavaScript (يحوّل إلى الدخول)، ومع JavaScript تظهر نافذة الخيارين --}}
                    <a href="{{ route('guest.report.access', [$verification, 'login']) }}" class="btn btn-site"
                       data-bs-toggle="modal" data-bs-target="#printGateModal" role="button">
                        <i class="bi bi-printer me-2" aria-hidden="true"></i>{{ __('reports.print.button') }}
                    </a>
                    <button type="button" class="btn btn-site-ghost" data-bs-toggle="modal" data-bs-target="#guestAccountModal">
                        <i class="bi bi-file-earmark-arrow-down me-2" aria-hidden="true"></i>{{ __('verifications.show.download_pdf') }}
                    </button>
                </div>
            @endif
        </header>

        <div class="site-note mb-4">
            <i class="bi bi-person" aria-hidden="true"></i>
            <span>{{ __('home_upload.guest_result_notice') }} <a href="{{ route('register') }}">{{ __('auth.login.create_account') }}</a></span>
        </div>

        @if (! $hasResult)
            {{-- حالتا الفشل والتحليل غير المكتمل: لا نتيجة تُعرض ولا تقدير مصطنع --}}
            <div class="case-empty">
                @if ($verification->status === RequestStatus::Failed)
                    <i class="bi bi-exclamation-octagon case-empty-icon is-fake" aria-hidden="true"></i>
                    <h2 class="site-h2">{{ __('landing.case.failed_title') }}</h2>
                    <p class="case-empty-reason">{{ __('ai_errors.'.($verification->error_message ?: 'unexpected')) }}</p>
                    <p class="site-lead">{{ __('landing.case.failed_hint') }}</p>
                    <div class="case-actions justify-content-center">
                        <a href="{{ route('home') }}#try-now" class="btn btn-site">{{ __('landing.case.try_again') }}</a>
                        <a href="{{ route('register') }}" class="btn btn-site-ghost">{{ __('home_upload.retry_needs_account') }}</a>
                    </div>
                @else
                    <span class="spinner-border case-empty-spinner" role="status" aria-hidden="true"></span>
                    <h2 class="site-h2">{{ __('landing.case.pending_title') }}</h2>
                    <p class="site-lead">{{ __('verifications.show.in_progress') }}</p>
                    <a href="{{ route('guest.progress', $verification) }}" class="btn btn-site">{{ __('verifications.show.follow_progress') }}</a>
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
            @endphp

            <div class="row g-4">
                <div class="col-lg-8">
                    {{-- الخلاصة بلغة مفهومة --}}
                    <section class="case-verdict is-{{ $classification->value }}" aria-labelledby="verdict-title">
                        <p class="case-verdict-label">{{ __('verifications.show.final_classification') }}</p>
                        <h2 id="verdict-title" class="case-verdict-title">
                            <i class="bi {{ $classification->icon() }}" aria-hidden="true"></i> {{ $classification->label() }}
                        </h2>
                        <p class="case-verdict-text">{{ $result->displayExplanation($media->media_type) }}</p>
                        <p class="case-verdict-meta">{{ __('verifications.show.risk_level') }}: <strong>{{ $result->risk_level->label() }}</strong></p>
                    </section>

                    <div class="case-caution">
                        <strong>{{ __('landing.case.estimate_title') }}.</strong>
                        {{ __('landing.case.estimate_text') }}
                    </div>

                    {{-- الأرقام --}}
                    <section class="case-block" aria-labelledby="numbers-title">
                        <h2 id="numbers-title" class="case-block-title">{{ __('landing.case.probabilities') }}</h2>
                        <div class="case-numbers">
                            <div class="case-confidence">
                                <span class="case-confidence-value ltr-text">{{ number_format($result->confidence_score, 1) }}<small>%</small></span>
                                <span class="case-confidence-label">{{ __('verifications.show.confidence') }}</span>
                                <span class="case-confidence-help">{{ __('verifications.show.confidence_help') }}</span>
                            </div>
                            <div class="case-bars">
                                @foreach ([['authenticity_probability', 'authenticity', 'real'], ['manipulation_probability', 'manipulation', 'fake']] as [$field, $label, $tone])
                                    <div class="case-bar">
                                        <div class="case-bar-head">
                                            <span>{{ __('verifications.show.'.$label) }}</span>
                                            <strong class="ltr-text">{{ number_format($result->{$field}, 1) }}%</strong>
                                        </div>
                                        <div class="case-bar-track" role="progressbar" aria-valuenow="{{ round($result->{$field}) }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('verifications.show.'.$label) }}">
                                            <span class="is-{{ $tone }}" style="width: {{ min(100, max(0, $result->{$field})) }}%"></span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </section>

                    {{-- المؤشرات التفسيرية --}}
                    <section class="case-block" aria-labelledby="indicators-title">
                        <h2 id="indicators-title" class="case-block-title">{{ __('verifications.show.indicators') }} <span class="case-count">{{ $indicators->count() }}</span></h2>
                        @if ($indicators->isEmpty())
                            <p class="case-block-empty">{{ __('verifications.show.no_indicators') }}</p>
                        @else
                            <p class="case-block-lead">{{ __('landing.case.indicators_text') }}</p>
                            <ol class="indicators">
                                @foreach ($indicators as $indicator)
                                    <li class="indicator">
                                        <div class="indicator-head">
                                            <h3 class="indicator-title">{{ $indicator->title() }}</h3>
                                            <span class="sev is-{{ $indicator->severity->value }}">{{ $indicator->severity->label() }}</span>
                                        </div>
                                        <p class="indicator-meta">
                                            {{ $indicator->category === 'model' ? __('indicators.from_model') : __('indicators.supporting') }}
                                            @if (! empty($indicator->details['experimental'])) · {{ __('indicators.experimental') }} @endif
                                            @if ($indicator->score !== null) · {{ __('verifications.show.indicator_score') }} <span class="ltr-text">{{ number_format($indicator->score, 0) }}%</span> @endif
                                            @if ($indicator->timestamp_start !== null)
                                                · {{ __('verifications.show.at_time') }}
                                                <span class="ltr-text">{{ $fmtTime($indicator->timestamp_start) }}@if ($indicator->timestamp_end !== null && $indicator->timestamp_end != $indicator->timestamp_start) – {{ $fmtTime($indicator->timestamp_end) }}@endif</span>
                                            @endif
                                        </p>
                                        @if ($indicator->explanation())
                                            <p class="indicator-text">{{ $indicator->explanation() }}</p>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </section>

                    @if ($frames->isNotEmpty())
                        <section class="case-block" aria-labelledby="frames-title">
                            <h2 id="frames-title" class="case-block-title">{{ __('verifications.show.suspicious_frames') }}</h2>
                            <div class="frames">
                                @foreach ($frames as $frame)
                                    <a class="frame" href="{{ route('guest.frame', [$verification, $frame]) }}" target="_blank" rel="noopener">
                                        <img loading="lazy" src="{{ route('guest.frame', [$verification, $frame]) }}"
                                             alt="{{ __('verifications.show.frame_alt', ['time' => $fmtTime($frame->timestamp_start) ?? '']) }}">
                                        <span class="ltr-text">{{ $fmtTime($frame->timestamp_start) }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    @if ($timeline->isNotEmpty() && $media->media_type !== MediaType::Image)
                        <section class="case-block" aria-labelledby="timeline-title">
                            <h2 id="timeline-title" class="case-block-title">{{ __('verifications.show.timeline') }}</h2>
                            <ol class="timeline">
                                @foreach ($timeline as $item)
                                    <li><span class="ltr-text">{{ $fmtTime($item->timestamp_start) }}</span> {{ $item->title() }}</li>
                                @endforeach
                            </ol>
                        </section>
                    @endif

                    <section class="case-block" aria-labelledby="actions-title">
                        <h2 id="actions-title" class="case-block-title">{{ __('verifications.show.recommendations') }}</h2>
                        <ul class="plain-list">
                            @foreach (__('verifications.recommendations.'.$classification->value) as $tip)
                                <li>{{ $tip }}</li>
                            @endforeach
                        </ul>
                    </section>
                </div>

                {{-- العمود الجانبي: المعاينة وبيانات الملف والنموذج --}}
                <aside class="col-lg-4" aria-label="{{ __('landing.case.details') }}">
                    <section class="case-block">
                        <h2 class="case-block-title">{{ __('verifications.show.preview') }}</h2>
                        @if ($media->existsOnDisk())
                            <div class="case-preview">
                                @if ($media->media_type === MediaType::Image)
                                    <img src="{{ route('guest.media', $verification) }}" alt="{{ __('verifications.show.preview_alt') }}">
                                @elseif ($media->media_type === MediaType::Video)
                                    <video controls preload="metadata" src="{{ route('guest.media', $verification) }}"></video>
                                @else
                                    <audio controls preload="metadata" src="{{ route('guest.media', $verification) }}"></audio>
                                @endif
                            </div>
                        @else
                            <p class="case-block-empty">{{ __('verifications.show.file_unavailable') }}</p>
                        @endif
                    </section>

                    <section class="case-block">
                        <h2 class="case-block-title">{{ __('verifications.show.file_info') }}</h2>
                        <dl class="facts">
                            <div><dt>{{ __('verifications.show.file_type') }}</dt><dd>{{ $media->media_type->label() }} <span class="ltr-text muted">{{ $media->mime_type }}</span></dd></div>
                            <div><dt>{{ __('verifications.show.file_size') }}</dt><dd class="ltr-text">{{ $media->humanSize() }}</dd></div>
                            @if ($media->width && $media->height)
                                <div><dt>{{ __('verifications.show.dimensions') }}</dt><dd class="ltr-text">{{ $media->width }} × {{ $media->height }}</dd></div>
                            @endif
                            @if ($media->duration_seconds)
                                <div><dt>{{ __('verifications.show.duration') }}</dt><dd class="ltr-text">{{ $fmtTime($media->duration_seconds) }}</dd></div>
                            @endif
                            <div class="is-block"><dt>{{ __('verifications.show.sha256') }}</dt><dd class="hash">{{ $media->sha256 }}</dd></div>
                        </dl>
                    </section>

                    <section class="case-block">
                        <h2 class="case-block-title">{{ __('verifications.show.model_info') }}</h2>
                        <dl class="facts">
                            <div><dt>{{ __('verifications.show.model_name') }}</dt><dd class="ltr-text text-break">{{ $result->model_name }}</dd></div>
                            <div><dt>{{ __('verifications.show.model_version') }}</dt><dd class="ltr-text">{{ $result->model_version }}</dd></div>
                            @if ($result->processing_time !== null)
                                <div><dt>{{ __('verifications.show.processing_time') }}</dt><dd>{{ __('verifications.show.seconds', ['value' => number_format($result->processing_time, 1)]) }}</dd></div>
                            @endif
                        </dl>
                    </section>
                </aside>
            </div>

            <section class="case-limits" aria-labelledby="limits-title">
                <h2 id="limits-title" class="case-block-title">{{ __('verifications.disclaimer_title') }}</h2>
                <p>{{ __('verifications.disclaimer_long') }}</p>
                <ul class="plain-list">
                    @foreach (__('verifications.limits') as $limit)
                        <li>{{ $limit }}</li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</section>

{{-- نافذة تنزيل PDF: متاحة للمستخدمين المسجَّلين فقط --}}
<div class="modal fade site-modal" id="guestAccountModal" tabindex="-1" aria-labelledby="guestAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('common.close') }}"></button>
                <h2 class="site-modal-title" id="guestAccountModalLabel">{{ __('home_upload.modal_title') }}</h2>
                <p class="site-modal-text">{{ __('home_upload.modal_text') }}</p>
                <div class="site-modal-actions">
                    <a href="{{ route('register') }}" class="btn btn-site">{{ __('auth.login.create_account') }}</a>
                    <button type="button" class="btn btn-site-ghost" data-bs-dismiss="modal">{{ __('home_upload.modal_later') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>

@if ($hasResult)
    {{-- طباعة التقرير تتطلب حسابًا: بعد الدخول أو التسجيل يعود المستخدم إلى هذه النتيجة نفسها (guest.claim) --}}
    <div class="modal fade site-modal" id="printGateModal" tabindex="-1" aria-labelledby="printGateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('common.close') }}"></button>
                    <span class="site-modal-icon" aria-hidden="true"><i class="bi bi-printer"></i></span>
                    <h2 class="site-modal-title" id="printGateModalLabel">{{ __('home_upload.print_modal_title') }}</h2>
                    <p class="site-modal-text">{{ __('home_upload.print_modal_text') }}</p>
                    <div class="site-modal-actions">
                        <a href="{{ route('guest.report.access', [$verification, 'login']) }}" class="btn btn-site">{{ __('auth.login.title') }}</a>
                        <a href="{{ route('guest.report.access', [$verification, 'register']) }}" class="btn btn-site-ghost">{{ __('auth.register.title') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection
