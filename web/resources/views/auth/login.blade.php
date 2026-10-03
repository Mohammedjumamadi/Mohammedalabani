@extends('layouts.auth')

@section('title', __('auth.login.title'))

@section('content')
    <header class="auth-head">
        <h1 class="auth-title">{{ __('auth.login.heading') }}</h1>
        <p class="auth-sub">{{ __('auth.login.subheading') }}</p>
    </header>

    <form method="POST" action="{{ route('login') }}" novalidate data-field-states>
        @csrf

        <x-form.input name="email" type="email" :label="__('validation.attributes.email')" required autocomplete="username" ltr autofocus maxlength="190" />
        <x-form.input name="password" type="password" :label="__('validation.attributes.password')" required autocomplete="current-password" ltr reveal />

        <div class="auth-row">
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1" @checked(old('remember'))>
                <label class="form-check-label" for="remember">{{ __('auth.login.remember') }}</label>
            </div>
            <a href="{{ route('password.request') }}">{{ __('auth.login.forgot') }}</a>
        </div>

        <button type="submit" class="btn btn-site btn-lg w-100">{{ __('auth.login.submit') }}</button>
    </form>

    @if (\App\Models\SystemSetting::get('registration_open', true))
        <p class="auth-switch">
            {{ __('auth.login.no_account') }}
            <a href="{{ route('register') }}">{{ __('auth.login.create_account') }}</a>
        </p>
    @endif
@endsection
