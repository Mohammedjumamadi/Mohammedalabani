@props(['value', 'label', 'tone' => 'accent'])

<div>
    <div class="d-flex justify-content-between small mb-1">
        <span>{{ $label }}</span>
        <strong class="ltr-text">{{ number_format($value, 1) }}%</strong>
    </div>
    <div class="prob-meter" role="progressbar" aria-valuenow="{{ round($value) }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $label }}">
        <span class="bar-{{ $tone }}" style="width: {{ min(100, max(0, $value)) }}%"></span>
    </div>
</div>
