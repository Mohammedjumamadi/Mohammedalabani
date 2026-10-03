@extends('layouts.auth')

@section('title', __('auth.forgot.title'))

@section('content')
    <header class="auth-head">
        <h1 class="auth-title">{{ __('auth.forgot.heading') }}</h1>
        <p class="auth-sub">{{ __('auth.forgot.subheading') }}</p>
    </header>

    <form method="POST" action="{{ route('password.email') }}" novalidate data-field-states>
        @csrf
        <x-form.input name="email" type="email" :label="__('validation.attributes.email')" required autocomplete="email" ltr autofocus />
        <button type="submit" class="btn btn-site btn-lg w-100">{{ __('auth.forgot.submit') }}</button>
    </form>

    <p class="auth-switch">
        <a href="{{ route('login') }}">{{ __('auth.forgot.back') }}</a>
    </p>
@endsection
