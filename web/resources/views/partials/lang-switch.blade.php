{{-- مبدّل لغة واضح: يعرض اللغتين معًا ويميّز الحالية؛ الاختيار يُحفظ في الجلسة وفي حساب المستخدم (LocaleController) --}}
@php $current = app()->getLocale(); @endphp
<div class="lang-switch" role="group" aria-label="{{ __('landing.language') }}">
    <i class="bi bi-translate lang-switch-icon" aria-hidden="true"></i>
    @foreach (config('aimv.locales') as $code => $locale)
        <a href="{{ route('locale.switch', $code) }}" lang="{{ $code }}" hreflang="{{ $code }}"
           @class(['lang-switch-opt', 'is-active' => $code === $current])
           @if ($code === $current) aria-current="true" @endif>{{ $locale['name'] }}</a>
    @endforeach
</div>
