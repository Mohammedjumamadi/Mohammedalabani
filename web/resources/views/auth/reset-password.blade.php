@extends('layouts.auth')

@section('title', __('auth.reset.title'))

@section('content')
    <header class="auth-head">
        <h1 class="auth-title">{{ __('auth.reset.heading') }}</h1>
        <p class="auth-sub">{{ __('auth.reset.subheading') }}</p>
    </header>

    <form method="POST" action="{{ route('password.update') }}" novalidate data-field-states>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <x-form.input name="email" type="email" :label="__('validation.attributes.email')" :value="$email" required autocomplete="username" ltr />
        <x-form.input name="password" type="password" :label="__('validation.attributes.password')" required autocomplete="new-password" ltr reveal :hint="__('common.password_hint')" />
        <x-form.input name="password_confirmation" type="password" :label="__('validation.attributes.password_confirmation')" required autocomplete="new-password" ltr reveal />

        <button type="submit" class="btn btn-site btn-lg w-100">{{ __('auth.reset.submit') }}</button>
    </form>
@endsection
