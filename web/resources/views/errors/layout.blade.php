@extends('layouts.base')

@section('title', __('errors.'.$code.'.title'))

@section('body')
<main id="main" class="error-page-shell">

    {{-- Background decoration --}}
    <div class="error-bg-orb error-bg-orb-1" aria-hidden="true"></div>
    <div class="error-bg-orb error-bg-orb-2" aria-hidden="true"></div>

    <div class="error-card">

        {{-- Error icon --}}
        <div class="error-icon-wrap" aria-hidden="true">
            <i class="bi {{ $icon ?? 'bi-exclamation-triangle-fill' }}"></i>
        </div>

        {{-- Code --}}
        <div class="error-code ltr-text">{{ $code }}</div>

        {{-- Title & text --}}
        <h1 class="error-title">{{ __('errors.'.$code.'.title') }}</h1>
        <p class="error-text text-muted">{{ __('errors.'.$code.'.text') }}</p>

        {{-- Actions --}}
        <div class="d-flex gap-3 justify-content-center flex-wrap mt-4">
            <a href="{{ url('/') }}" class="btn btn-primary btn-lg">
                <i class="bi bi-house-fill me-2" aria-hidden="true"></i>{{ __('errors.home') }}
            </a>
            <a href="javascript:history.back()" class="btn btn-outline-primary btn-lg">
                {{ __('common.back') }}
            </a>
        </div>

        {{-- Brand link --}}
        <div class="mt-5">
            <a href="{{ route('home') }}" class="d-inline-flex align-items-center gap-2 text-muted text-decoration-none small">
                <span class="brand-mark" style="width:1.6rem;height:1.6rem;font-size:.8rem" aria-hidden="true">
                    <i class="bi bi-shield-check"></i>
                </span>
                {{ __('app.name') }}
            </a>
        </div>
    </div>

</main>
@endsection
