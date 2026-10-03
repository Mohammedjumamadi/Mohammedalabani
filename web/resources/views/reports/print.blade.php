@extends('layouts.base')

@section('title', __('reports.print.title').' '.$verification->reference_number)

{{--
    تقرير الطباعة: يعرض فقط بيانات نتيجة التحليل المخزّنة فعلًا (لا قيم افتراضية أو مولّدة).
    الوصول محمي على الخادم (auth + سياسة view) في ReportController::print.
--}}
@php
    use App\Enums\MediaType;

    $classification = $result->classification;
    $fmtTime = fn (?float $s) => $s === null ? null : sprintf('%02d:%02d', intdiv((int) $s, 60), (int) $s % 60);
    $fmtDate = fn ($d) => $d?->translatedFormat('j F Y - H:i');
    $analyzedAt = $result->completed_at ?? $verification->completed_at;
    $backUrl = route('verifications.show', $verification);
@endphp

@push('head')
<meta name="robots" content="noindex, nofollow">
<style>
    body { background: #eef2f7; }
    .print-toolbar {
        position: sticky; top: 0; z-index: 10; background: #0b1a30; color: #e2e8f0;
        padding: .6rem 0; box-shadow: 0 2px 10px rgba(2, 8, 23, .25);
    }
    .print-toolbar .btn-light { font-weight: 700; }
    .print-sheet {
        max-width: 52rem; margin: 1.5rem auto; background: #fff; border-radius: .75rem;
        box-shadow: 0 10px 30px rgba(15, 36, 68, .12); padding: 2.25rem 2.5rem; color: #1e293b;
    }
    .print-sheet h1 { font-size: 1.45rem; font-weight: 800; color: #0f2444; margin: 0; }
    .print-sheet h2 {
        font-size: 1.02rem; font-weight: 800; color: #0f2444; margin: 1.5rem 0 .6rem;
        padding-bottom: .35rem; border-bottom: 2px solid #e2e8f0;
    }
    .rp-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; padding-bottom: 1rem; border-bottom: 3px solid #1d68bd; }
    .rp-brand { display: flex; align-items: center; gap: .7rem; }
    .rp-brand .brand-icon-box { width: 40px; height: 40px; font-size: 1.2rem; box-shadow: none; }
    .rp-sub { color: #64748b; font-size: .88rem; }
    .rp-ref { text-align: end; font-size: .82rem; color: #64748b; }
    .rp-ref strong { display: block; color: #0f2444; font-size: 1.05rem; direction: ltr; unicode-bidi: isolate; }
    .rp-verdict { border: 2px solid currentColor; border-radius: .75rem; padding: 1rem 1.2rem; margin-top: 1.25rem; }
    .rp-verdict .rp-verdict-title { font-size: 1.35rem; font-weight: 800; }
    .rp-verdict p { color: #1e293b; margin: .35rem 0 0; }
    .rp-metrics { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .75rem; margin-top: .9rem; }
    .rp-metric { border: 1px solid #e2e8f0; border-radius: .6rem; padding: .7rem .9rem; }
    .rp-metric .lbl { font-size: .78rem; color: #64748b; font-weight: 700; }
    .rp-metric .val { font-size: 1.35rem; font-weight: 800; color: #0f2444; direction: ltr; unicode-bidi: isolate; }
    .rp-bar { height: 6px; background: #e5e9f2; border-radius: 3px; overflow: hidden; margin-top: .3rem; }
    .rp-bar > span { display: block; height: 100%; }
    .rp-table { width: 100%; border-collapse: collapse; font-size: .88rem; }
    .rp-table th, .rp-table td { border: 1px solid #e2e8f0; padding: .5rem .65rem; vertical-align: top; text-align: start; }
    .rp-table th { background: #f1f5fb; color: #0f2444; width: 30%; font-weight: 700; }
    .rp-table.is-list th { width: auto; }
    .rp-ltr { direction: ltr; unicode-bidi: isolate; }
    .rp-hash { font-family: ui-monospace, Consolas, monospace; font-size: .76rem; word-break: break-all; direction: ltr; unicode-bidi: plaintext; }
    .rp-verify { display: flex; gap: 1rem; align-items: center; }
    .rp-verify img { width: 110px; height: 110px; flex-shrink: 0; }
    .rp-note { border: 1px solid #b45309; background: #fffbeb; border-radius: .6rem; padding: .75rem 1rem; font-size: .82rem; margin-top: 1.25rem; }
    .rp-footer { margin-top: 1.25rem; font-size: .75rem; color: #64748b; display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
    @media (max-width: 575.98px) {
        .print-sheet { margin: .75rem; padding: 1.25rem 1rem; }
        .rp-head { flex-direction: column; }
        .rp-ref { text-align: start; }
        .rp-metrics { grid-template-columns: 1fr; }
    }
    @media print {
        @page { size: A4; margin: 12mm; }
        body { background: #fff; }
        .print-toolbar { display: none !important; }
        .print-sheet { box-shadow: none; margin: 0; padding: 0; max-width: none; border-radius: 0; }
        .rp-verdict, .rp-metric, .rp-table tr, .rp-verify, .rp-note { break-inside: avoid; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
@endpush

@section('body')
<div class="print-toolbar no-print">
    <div class="container d-flex justify-content-between align-items-center gap-2 flex-wrap" style="max-width: 52rem;">
        <a href="{{ $backUrl }}" class="btn btn-sm btn-outline-light">
            <i class="bi {{ app()->getLocale() === 'ar' ? 'bi-arrow-right' : 'bi-arrow-left' }} me-1" aria-hidden="true"></i> {{ __('reports.print.back') }}
        </a>
        <div class="d-flex gap-2 align-items-center">
            <a href="{{ route('reports.pdf', $verification) }}" class="btn btn-sm btn-outline-light">
                <i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i> {{ __('verifications.show.download_pdf') }}
            </a>
            <button type="button" class="btn btn-sm btn-light" onclick="window.print()">
                <i class="bi bi-printer me-1" aria-hidden="true"></i> {{ __('reports.print.print_now') }}
            </button>
        </div>
    </div>
</div>

<main id="main" class="print-sheet">
    @if (session('success'))
        <div class="alert alert-success no-print py-2 small">{{ session('success') }}</div>
    @endif

    <header class="rp-head">
        <div class="rp-brand">
            <span class="brand-icon-box" aria-hidden="true"><i class="bi bi-shield-check"></i></span>
            <div>
                <h1>{{ __('reports.print.title') }}</h1>
                <div class="rp-sub">{{ __('app.name') }} · {{ __('reports.pdf.subtitle') }}</div>
            </div>
        </div>
        <div class="rp-ref">
            {{ __('reports.print.reference') }}
            <strong>{{ $verification->reference_number }}</strong>
        </div>
    </header>

    @if ($verification->is_demo)
        <div class="rp-note">{{ __('common.demo_data_help') }}</div>
    @endif

    {{-- ملخص النتيجة --}}
    <section class="rp-verdict {{ $classification->cssClass() }}" aria-label="{{ __('reports.print.summary') }}">
        <div class="small fw-bold opacity-75">{{ __('verifications.show.final_classification') }}</div>
        <div class="rp-verdict-title"><i class="bi {{ $classification->icon() }}" aria-hidden="true"></i> {{ $classification->label() }}</div>
        <p>{{ $result->displayExplanation($media->media_type) }}</p>
        <div class="small mt-2"><strong>{{ __('verifications.show.risk_level') }}:</strong> {{ $result->risk_level->label() }}</div>
    </section>

    <div class="rp-metrics">
        <div class="rp-metric">
            <div class="lbl">{{ __('verifications.show.confidence') }}</div>
            <div class="val">{{ number_format($result->confidence_score, 1) }}%</div>
        </div>
        <div class="rp-metric">
            <div class="lbl">{{ __('verifications.show.authenticity') }}</div>
            <div class="val" style="color:#15803d">{{ number_format($result->authenticity_probability, 1) }}%</div>
            <div class="rp-bar"><span style="width: {{ min(100, max(0, $result->authenticity_probability)) }}%; background:#15803d"></span></div>
        </div>
        <div class="rp-metric">
            <div class="lbl">{{ __('verifications.show.manipulation') }}</div>
            <div class="val" style="color:#b91c1c">{{ number_format($result->manipulation_probability, 1) }}%</div>
            <div class="rp-bar"><span style="width: {{ min(100, max(0, $result->manipulation_probability)) }}%; background:#b91c1c"></span></div>
        </div>
    </div>
    <div class="text-muted small mt-1">{{ __('verifications.show.confidence_help') }}</div>

    {{-- بيانات الملف والفحص --}}
    <h2>{{ __('reports.print.file') }}</h2>
    <table class="rp-table">
        <tr><th>{{ __('verifications.show.file_name') }}</th><td class="text-break">{{ $media->original_name }}</td></tr>
        <tr><th>{{ __('verifications.show.file_type') }}</th><td>{{ $media->media_type->label() }} <span class="rp-ltr text-muted">({{ $media->mime_type }})</span></td></tr>
        <tr><th>{{ __('verifications.show.file_size') }}</th><td class="rp-ltr">{{ $media->humanSize() }}</td></tr>
        @if ($media->width && $media->height)
            <tr><th>{{ __('verifications.show.dimensions') }}</th><td class="rp-ltr">{{ $media->width }} × {{ $media->height }}</td></tr>
        @endif
        @if ($media->duration_seconds)
            <tr><th>{{ __('verifications.show.duration') }}</th><td class="rp-ltr">{{ $fmtTime($media->duration_seconds) }}</td></tr>
        @endif
        <tr><th>{{ __('reports.print.uploaded_at') }}</th><td>{{ $fmtDate($media->created_at) }}</td></tr>
        @if ($analyzedAt)
            <tr><th>{{ __('reports.print.analyzed_at') }}</th><td>{{ $fmtDate($analyzedAt) }}</td></tr>
        @endif
        <tr><th>{{ __('verifications.show.sha256') }}</th><td class="rp-hash">{{ $media->sha256 }}</td></tr>
    </table>

    {{-- المؤشرات التفسيرية كما سجّلها التحليل --}}
    <h2>{{ __('reports.print.indicators') }}</h2>
    @if ($indicators->isEmpty())
        <p class="text-muted small mb-0">{{ __('verifications.show.no_indicators') }}</p>
    @else
        <table class="rp-table is-list">
            <thead>
                <tr>
                    <th style="width:34%">{{ __('verifications.show.indicators') }}</th>
                    <th style="width:14%">{{ __('reports.print.severity') }}</th>
                    <th style="width:11%">{{ __('reports.print.score') }}</th>
                    <th>{{ __('reports.print.details') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($indicators as $indicator)
                    <tr>
                        <td>
                            <strong>{{ $indicator->title() }}</strong>
                            <div class="text-muted" style="font-size:.75rem">
                                {{ $indicator->category === 'model' ? __('indicators.from_model') : __('indicators.supporting') }}@if (! empty($indicator->details['experimental'])) · {{ __('indicators.experimental') }}@endif
                            </div>
                        </td>
                        <td>{{ $indicator->severity->label() }}</td>
                        <td class="rp-ltr">{{ $indicator->score !== null ? number_format($indicator->score, 0).'%' : '—' }}</td>
                        <td>
                            {{ $indicator->explanation() }}
                            @if ($indicator->timestamp_start !== null)
                                <div class="mt-1"><strong>{{ __('reports.print.time') }}:</strong>
                                    <span class="rp-ltr">{{ $fmtTime($indicator->timestamp_start) }}@if ($indicator->timestamp_end !== null && $indicator->timestamp_end != $indicator->timestamp_start) – {{ $fmtTime($indicator->timestamp_end) }}@endif</span>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- النموذج المستخدم --}}
    <h2>{{ __('reports.print.model') }}</h2>
    <table class="rp-table">
        <tr><th>{{ __('verifications.show.model_name') }}</th><td class="rp-ltr">{{ $result->model_name }}</td></tr>
        <tr><th>{{ __('verifications.show.model_version') }}</th><td class="rp-ltr">{{ $result->model_version }}</td></tr>
        @if ($result->processing_time !== null)
            <tr><th>{{ __('verifications.show.processing_time') }}</th><td>{{ __('verifications.show.seconds', ['value' => number_format($result->processing_time, 1)]) }}</td></tr>
        @endif
        @if ($result->thresholds)
            <tr><th>{{ __('verifications.show.thresholds') }}</th><td>{{ __('verifications.show.thresholds_value', ['fake' => $result->thresholds['fake'], 'real' => $result->thresholds['real']]) }}</td></tr>
        @endif
    </table>

    {{-- التحقق من أصالة التقرير --}}
    <h2>{{ __('reports.pdf.verify_title') }}</h2>
    <div class="rp-verify">
        <img src="{{ $qr }}" alt="QR">
        <div class="small">
            <div>{{ __('reports.pdf.verify_text') }}</div>
            <div class="rp-ltr mt-1 text-break" style="color:#1d68bd">{{ $verifyUrl }}</div>
        </div>
    </div>

    <div class="rp-note">
        <strong>{{ __('verifications.disclaimer_title') }}:</strong> {{ __('verifications.disclaimer_long') }}
    </div>

    <div class="rp-footer">
        <span>{{ __('reports.print.printed_at') }}: {{ $fmtDate(now()) }}</span>
        <span class="rp-ltr">{{ $verification->reference_number }}</span>
    </div>
</main>

@if (request()->boolean('autoprint'))
    @push('scripts')
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 400));</script>
    @endpush
@endif
@endsection
