@extends('layouts.auth')

@section('title', __('auth.register.title'))

@php
    // عند رجوع أخطاء من الخادم يفتح المعالج على أول خطوة فيها خطأ
    $stepFields = [1 => ['name', 'email'], 2 => ['phone', 'city'], 3 => ['password', 'password_confirmation', 'terms']];
    $initialStep = 1;
    if ($errors->any()) {
        foreach ($stepFields as $step => $fields) {
            if ($errors->hasAny($fields)) { $initialStep = $step; break; }
        }
    }
@endphp

@section('content')
    <header class="auth-head">
        <h1 class="auth-title">{{ __('auth.register.heading') }}</h1>
        <p class="auth-sub">{{ __('auth.register.subheading') }}</p>
    </header>

    {{--
        معالج من 3 خطوات: (1) الاسم والبريد، (2) الهاتف والمدينة، (3) كلمة المرور وتأكيدها.
        الخطوات مجرد عرض: النموذج واحد ويُتحقق من كل حقوله على الخادم. دون JavaScript تظهر الخطوات كلها.
    --}}
    <form method="POST" action="{{ route('register') }}" novalidate data-field-states
          x-data="{
              step: {{ $initialStep }},
              total: 3,
              next() {
                  const fields = [...this.$refs['step' + this.step].querySelectorAll('input, select')];
                  const invalid = fields.find((f) => ! f.checkValidity());
                  if (invalid) { invalid.reportValidity(); return; }
                  this.step++;
                  this.$nextTick(() => this.$refs['step' + this.step].querySelector('input')?.focus());
              },
          }">
        @csrf

        <ol class="wizard" aria-label="{{ __('auth.register.steps.label') }}" x-cloak x-show="true">
            @foreach ([1, 2, 3] as $n)
                <li :class="{ 'is-active': step === {{ $n }}, 'is-done': step > {{ $n }} }" :aria-current="step === {{ $n }} ? 'step' : null">
                    <span class="wizard-num" aria-hidden="true">
                        <span x-show="step <= {{ $n }}">{{ $n }}</span><i class="bi bi-check-lg" x-show="step > {{ $n }}"></i>
                    </span>
                    <span class="wizard-label">{{ __('auth.register.steps.step_'.$n) }}</span>
                </li>
            @endforeach
        </ol>

        <fieldset x-ref="step1" x-show="step === 1">
            <legend class="visually-hidden">{{ __('auth.register.steps.step_1') }}</legend>
            <x-form.input name="name" :label="__('validation.attributes.name')" required autocomplete="name" autofocus maxlength="100" />
            <x-form.input name="email" type="email" :label="__('validation.attributes.email')" required autocomplete="email" ltr maxlength="190" />
        </fieldset>

        <fieldset x-ref="step2" x-show="step === 2">
            <legend class="visually-hidden">{{ __('auth.register.steps.step_2') }}</legend>
            <x-form.input name="phone" type="tel" :label="__('validation.attributes.phone')" required autocomplete="tel" ltr
                          maxlength="30" minlength="7" pattern="[0-9+\-\s()]+" :hint="__('auth.register.steps.phone_hint')" />
            <x-form.input name="city" :label="__('validation.attributes.city')" required autocomplete="address-level2" maxlength="100"
                          :placeholder="__('auth.register.steps.city_placeholder')" />
        </fieldset>

        <fieldset x-ref="step3" x-show="step === 3">
            <legend class="visually-hidden">{{ __('auth.register.steps.step_3') }}</legend>
            <x-form.input name="password" type="password" :label="__('validation.attributes.password')" required autocomplete="new-password" ltr reveal :hint="__('common.password_hint')" />
            <x-form.input name="password_confirmation" type="password" :label="__('validation.attributes.password_confirmation')" required autocomplete="new-password" ltr reveal />

            <div class="form-check mb-3">
                <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox" name="terms" id="terms" value="1" required @checked(old('terms'))>
                <label class="form-check-label" for="terms">
                    {{ __('auth.register.terms_prefix') }}
                    <a href="{{ route('pages.terms') }}" target="_blank" rel="noopener">{{ __('auth.register.terms_link') }}</a>
                    {{ __('auth.register.terms_and') }}
                    <a href="{{ route('pages.privacy') }}" target="_blank" rel="noopener">{{ __('auth.register.privacy_link') }}</a>
                </label>
                @error('terms')<div class="invalid-feedback d-block"><i class="bi bi-exclamation-circle" aria-hidden="true"></i> {{ $message }}</div>@enderror
            </div>
        </fieldset>

        <div class="wizard-actions">
            <button type="button" class="btn btn-site-ghost btn-lg" x-cloak x-show="step > 1" @click="step--">{{ __('auth.register.steps.back') }}</button>
            <button type="button" class="btn btn-site btn-lg" x-cloak x-show="step < total" @click="next()">{{ __('auth.register.steps.next') }}</button>
            <button type="submit" class="btn btn-site btn-lg" x-show="step === total">{{ __('auth.register.submit') }}</button>
        </div>
    </form>

    <p class="auth-switch">
        {{ __('auth.register.have_account') }}
        <a href="{{ route('login') }}">{{ __('auth.login.submit') }}</a>
    </p>
@endsection
