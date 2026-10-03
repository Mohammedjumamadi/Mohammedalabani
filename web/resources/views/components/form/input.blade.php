@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'autocomplete' => null,
    'hint' => null,
    'ltr' => false,
    // زر إظهار/إخفاء كلمة المرور (اختياري؛ يعمل عبر data-reveal في resources/js/app.js)
    'reveal' => false,
])

@php
    $revealable = $reveal && $type === 'password';
    $describedBy = $errors->has($name) ? $name.'-error' : ($hint ? $name.'-hint' : null);
@endphp

<div class="mb-3">
    <label for="{{ $name }}" class="form-label">
        {{ $label }}@if ($required)<span class="text-danger" aria-hidden="true"> *</span>@endif
    </label>
    @if ($revealable)<div class="input-reveal">@endif
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ $type === 'password' ? '' : old($name, $value) }}"
        @class(['form-control', 'is-invalid' => $errors->has($name)])
        @if ($ltr) dir="ltr" style="text-align:start" @endif
        @if ($required) required @endif
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if ($errors->has($name)) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes }}
    >
    @if ($revealable)
        <button type="button" class="input-reveal-btn" data-reveal="{{ $name }}" aria-pressed="false" aria-controls="{{ $name }}"
                data-label-show="{{ __('landing.auth.show_password') }}" data-label-hide="{{ __('landing.auth.hide_password') }}"
                aria-label="{{ __('landing.auth.show_password') }}" title="{{ __('landing.auth.show_password') }}">
            <i class="bi bi-eye" aria-hidden="true"></i>
        </button>
    </div>
    @endif
    @error($name)
        <div class="invalid-feedback d-block" id="{{ $name }}-error"><i class="bi bi-exclamation-circle" aria-hidden="true"></i> {{ $message }}</div>
    @enderror
    @if ($hint)
        <div class="form-text" id="{{ $name }}-hint">{{ $hint }}</div>
    @endif
</div>
