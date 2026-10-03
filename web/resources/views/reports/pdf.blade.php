@php
    $dir = $rtl ? 'rtl' : 'ltr';
    $align = $rtl ? 'right' : 'left';
    $classification = $result->classification;
    $color = ['real' => '#15803d', 'uncertain' => '#b45309', 'fake' => '#b91c1c'][$classification->value];
    $bg = ['real' => '#f0fdf4', 'uncertain' => '#fffbeb', 'fake' => '#fef2f2'][$classification->value];
    $symbol = ['real' => '✔', 'uncertain' => '!', 'fake' => '✖'][$classification->value];
    $fmtTime = fn (?float $s) => $s === null ? '' : sprintf('%02d:%02d', intdiv((int) $s, 60), (int) $s % 60);
    $explanation = $result->displayExplanation($media->media_type);
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <style>
        body { font-size: 11pt; color: #1f2937; direction: {{ $dir }}; text-align: {{ $align }}; }
        h1 { color: #0b2a5b; font-size: 20pt; margin: 0 0 4px 0; }
        h2 { color: #0b2a5b; font-size: 13pt; margin: 16px 0 6px 0; border-bottom: 1px solid #dde3ee; padding-bottom: 3px; }
        table { border-collapse: collapse; width: 100%; }
        td, th { padding: 5px 7px; vertical-align: top; text-align: {{ $align }}; }
        .grid td, .grid th { border: 1px solid #dde3ee; font-size: 10pt; }
        .grid th { background: #eef3fb; color: #0b2a5b; }
        .muted { color: #4b5563; }
        .ltr { direction: ltr; unicode-bidi: embed; }
        .verdict { border: 2px solid {{ $color }}; background: {{ $bg }}; padding: 10px 14px; }
        .verdict-title { color: {{ $color }}; font-size: 17pt; font-weight: bold; }
        .bar { background: #e5e9f2; height: 9px; }
        .warn { border: 1px solid #b45309; background: #fffbeb; padding: 8px 12px; font-size: 9.5pt; }
        .hash { font-family: monospace; font-size: 8.5pt; direction: ltr; text-align: left; }
    </style>
</head>
<body>

<table>
    <tr>
        <td style="width:70%">
            <h1>{{ __('app.name') }}</h1>
            <div class="muted">{{ __('reports.pdf.subtitle') }}</div>
        </td>
        <td style="text-align:{{ $rtl ? 'left' : 'right' }}">
            <div class="muted" style="font-size:9pt">{{ __('verifications.show.reference') }}</div>
            <div class="ltr" style="font-size:13pt;font-weight:bold;color:#0b2a5b">{{ $verification->reference_number }}</div>
            <div class="muted" style="font-size:9pt">{{ ($result->completed_at ?? now())->translatedFormat('j F Y - H:i') }}</div>
        </td>
    </tr>
</table>

@if ($verification->is_demo)
    <div class="warn" style="margin-top:8px">{{ __('common.demo_data_help') }}</div>
@endif

<h2>{{ __('verifications.show.final_classification') }}</h2>
<div class="verdict">
    <div class="verdict-title"><span style="font-family:dejavusans">{{ $symbol }}</span> {{ $classification->label() }}</div>
    <div style="margin-top:4px">{{ $explanation }}</div>
    <div style="margin-top:6px;font-size:10pt"><strong>{{ __('verifications.show.risk_level') }}:</strong> {{ $result->risk_level->label() }}</div>
</div>

<table class="grid" style="margin-top:10px">
    <tr>
        <th style="width:34%">{{ __('verifications.show.confidence') }}</th>
        <th style="width:33%">{{ __('verifications.show.authenticity') }}</th>
        <th style="width:33%">{{ __('verifications.show.manipulation') }}</th>
    </tr>
    <tr>
        <td class="ltr" style="font-size:14pt;font-weight:bold">{{ number_format($result->confidence_score, 1) }}%</td>
        <td><span class="ltr" style="font-weight:bold;color:#15803d">{{ number_format($result->authenticity_probability, 1) }}%</span>
            <div class="bar"><div style="background:#15803d;height:9px;width:{{ min(100, $result->authenticity_probability) }}%"></div></div></td>
        <td><span class="ltr" style="font-weight:bold;color:#b91c1c">{{ number_format($result->manipulation_probability, 1) }}%</span>
            <div class="bar"><div style="background:#b91c1c;height:9px;width:{{ min(100, $result->manipulation_probability) }}%"></div></div></td>
    </tr>
</table>
<div class="muted" style="font-size:9pt;margin-top:3px">{{ __('verifications.show.confidence_help') }}</div>

<h2>{{ __('verifications.show.indicators') }}</h2>
@if ($indicators->isEmpty())
    <div class="muted">{{ __('verifications.show.no_indicators') }}</div>
@else
    <table class="grid">
        <tr>
            <th style="width:32%">{{ __('verifications.show.indicators') }}</th>
            <th style="width:14%">{{ __('verifications.index.status') }}</th>
            <th style="width:12%">{{ __('verifications.show.indicator_score') }}</th>
            <th>{{ __('verifications.show.at_time') }} / {{ __('common.view') }}</th>
        </tr>
        @foreach ($indicators as $indicator)
            <tr>
                <td>{{ $indicator->title() }}<br><span class="muted" style="font-size:8.5pt">{{ $indicator->category === 'model' ? __('indicators.from_model') : __('indicators.supporting') }}@if (! empty($indicator->details['experimental'])) · {{ __('indicators.experimental') }}@endif</span></td>
                <td>{{ $indicator->severity->label() }}</td>
                <td class="ltr">{{ $indicator->score !== null ? number_format($indicator->score, 0).'%' : '—' }}</td>
                <td style="font-size:9pt">
                    {{ $indicator->explanation() }}
                    @if ($indicator->timestamp_start !== null)
                        <br><strong class="ltr">{{ $fmtTime($indicator->timestamp_start) }}@if ($indicator->timestamp_end !== null && $indicator->timestamp_end != $indicator->timestamp_start) – {{ $fmtTime($indicator->timestamp_end) }}@endif</strong>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
@endif

<h2>{{ __('verifications.show.file_info') }}</h2>
<table class="grid">
    <tr><th style="width:28%">{{ __('verifications.show.file_name') }}</th><td>{{ $media->original_name }}</td></tr>
    <tr><th>{{ __('verifications.show.file_type') }}</th><td>{{ $media->media_type->label() }} <span class="ltr muted">({{ $media->mime_type }})</span></td></tr>
    <tr><th>{{ __('verifications.show.file_size') }}</th><td class="ltr">{{ $media->humanSize() }}</td></tr>
    @if ($media->width && $media->height)<tr><th>{{ __('verifications.show.dimensions') }}</th><td class="ltr">{{ $media->width }} × {{ $media->height }}</td></tr>@endif
    @if ($media->duration_seconds)<tr><th>{{ __('verifications.show.duration') }}</th><td class="ltr">{{ $fmtTime($media->duration_seconds) }}</td></tr>@endif
    <tr><th>{{ __('verifications.show.uploaded_at') }}</th><td>{{ $media->created_at->translatedFormat('j F Y - H:i') }}</td></tr>
    <tr><th>{{ __('verifications.show.status') }}</th><td>{{ $verification->status->label() }}</td></tr>
    <tr><th>{{ __('verifications.show.sha256') }}</th><td class="hash">{{ $media->sha256 }}</td></tr>
</table>

<h2>{{ __('verifications.show.model_info') }}</h2>
<table class="grid">
    <tr><th style="width:28%">{{ __('verifications.show.model_name') }}</th><td class="ltr">{{ $result->model_name }}</td></tr>
    <tr><th>{{ __('verifications.show.model_version') }}</th><td class="ltr">{{ $result->model_version }}</td></tr>
    @if ($result->modelVersion?->source_url)<tr><th>{{ __('verifications.show.model_source') }}</th><td class="ltr" style="font-size:9pt">{{ $result->modelVersion->source_url }}</td></tr>@endif
    <tr><th>{{ __('verifications.show.processing_time') }}</th><td>{{ __('verifications.show.seconds', ['value' => number_format($result->processing_time ?? 0, 1)]) }}</td></tr>
    @if ($result->thresholds)<tr><th>{{ __('verifications.show.thresholds') }}</th><td>{{ __('verifications.show.thresholds_value', ['fake' => $result->thresholds['fake'], 'real' => $result->thresholds['real']]) }}</td></tr>@endif
</table>

<h2>{{ __('verifications.show.recommendations') }}</h2>
<ul style="margin:0;padding-{{ $rtl ? 'right' : 'left' }}:18px">
    @foreach (__('verifications.recommendations.'.$classification->value) as $tip)
        <li>{{ $tip }}</li>
    @endforeach
</ul>

<h2>{{ __('reports.pdf.verify_title') }}</h2>
<table>
    <tr>
        <td style="width:150px"><img src="{{ $qr }}" width="130" height="130" alt="QR"></td>
        <td>
            <div>{{ __('reports.pdf.verify_text') }}</div>
            <div class="ltr" style="margin-top:6px;font-size:9pt;color:#0e7490">{{ $verifyUrl }}</div>
            <div style="margin-top:6px"><strong>{{ __('verifications.show.reference') }}:</strong> <span class="ltr">{{ $verification->reference_number }}</span></div>
        </td>
    </tr>
</table>

<div class="warn" style="margin-top:14px">
    <strong>{{ __('verifications.disclaimer_title') }}:</strong> {{ __('verifications.disclaimer_long') }}
    <ul style="margin:4px 0 0 0;padding-{{ $rtl ? 'right' : 'left' }}:18px">
        @foreach (__('verifications.limits') as $limit)<li>{{ $limit }}</li>@endforeach
    </ul>
</div>

</body>
</html>
