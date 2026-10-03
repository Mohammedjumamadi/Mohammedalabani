@extends('layouts.panel')

@section('title', __('settings.title'))

@section('content')
    <div class="page-head">
        <h1>{{ __('settings.title') }}</h1>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <section class="aimv-card h-100" aria-labelledby="lang-title">
                <div class="card-head"><h2 id="lang-title">{{ __('settings.language') }}</h2></div>
                <div class="card-body-pad">
                    <p class="text-muted">{{ __('settings.language_help') }}</p>
                    <div class="d-flex gap-2 flex-wrap">
                        @foreach (config('aimv.locales') as $code => $meta)
                            <a href="{{ route('locale.switch', $code) }}"
                               class="btn {{ app()->getLocale() === $code ? 'btn-primary' : 'btn-outline-primary' }}"
                               @if (app()->getLocale() === $code) aria-current="true" @endif>
                                {{ $meta['name'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        </div>

        <div class="col-lg-6">
            <section class="aimv-card" aria-labelledby="pw-title">
                <div class="card-head"><h2 id="pw-title">{{ __('settings.change_password') }}</h2></div>
                <form method="POST" action="{{ route('settings.password') }}" class="card-body-pad" novalidate>
                    @csrf
                    @method('PUT')
                    <x-form.input name="current_password" type="password" :label="__('validation.attributes.current_password')" required autocomplete="current-password" ltr />
                    <x-form.input name="password" type="password" :label="__('settings.new_password')" required autocomplete="new-password" ltr :hint="__('common.password_hint')" />
                    <x-form.input name="password_confirmation" type="password" :label="__('validation.attributes.password_confirmation')" required autocomplete="new-password" ltr />
                    <button type="submit" class="btn btn-primary">{{ __('settings.update_password') }}</button>
                </form>
            </section>
        </div>

        <div class="col-12">
            <section class="aimv-card border-danger" aria-labelledby="data-title">
                <div class="card-head"><h2 id="data-title" class="text-danger">{{ __('settings.data_title') }}</h2></div>
                <div class="card-body-pad">
                    <p class="text-muted">{{ __('settings.data_help', ['days' => \App\Models\SystemSetting::get('retention_days')]) }}</p>
                    <form method="POST" action="{{ route('settings.data.delete') }}" class="row g-2 align-items-end" novalidate
                          onsubmit="return confirm(@js(__('settings.data_confirm')))">
                        @csrf @method('DELETE')
                        <div class="col-md-5">
                            <x-form.input name="delete_password" type="password" :label="__('validation.attributes.current_password')" required autocomplete="current-password" ltr />
                        </div>
                        <div class="col-md-4 mb-3">
                            <button class="btn btn-danger"><i class="bi bi-trash" aria-hidden="true"></i> {{ __('settings.data_button') }}</button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>
@endsection
