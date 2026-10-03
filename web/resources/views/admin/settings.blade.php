@extends('layouts.panel')

@section('panel', 'admin')
@section('title', __('nav.admin.settings'))

@section('content')
    <div class="page-head"><h1>{{ __('nav.admin.settings') }}</h1></div>

    <form method="POST" action="{{ route('admin.settings.update') }}" novalidate>
        @csrf @method('PUT')
        <div class="row g-4">
            <div class="col-lg-6">
                <section class="aimv-card h-100" aria-labelledby="c1">
                    <div class="card-head"><h2 id="c1">{{ __('admin.settings.classification') }}</h2></div>
                    <div class="card-body-pad">
                        <p class="text-muted small">{{ __('admin.settings.classification_help') }}</p>
                        <x-form.input name="threshold_fake" type="number" min="2" max="100" :label="__('admin.settings.threshold_fake')" :value="$settings['threshold_fake']" required :hint="__('admin.settings.threshold_fake_help')" />
                        <x-form.input name="threshold_real" type="number" min="1" max="99" :label="__('admin.settings.threshold_real')" :value="$settings['threshold_real']" required :hint="__('admin.settings.threshold_real_help')" />
                        <div class="notice-box small">{{ __('admin.settings.threshold_note') }}</div>
                    </div>
                </section>
            </div>

            <div class="col-lg-6">
                <section class="aimv-card h-100" aria-labelledby="c2">
                    <div class="card-head"><h2 id="c2">{{ __('admin.settings.uploads') }}</h2></div>
                    <div class="card-body-pad">
                        <x-form.input name="max_upload_mb" type="number" min="1" max="2048" :label="__('admin.settings.max_upload')" :value="$settings['max_upload_mb']" required :hint="__('admin.settings.server_limit', ['size' => $serverLimit])" />
                        <x-form.input name="retention_days" type="number" min="1" max="3650" :label="__('admin.settings.retention')" :value="$settings['retention_days']" required :hint="__('admin.settings.retention_help')" />
                        @foreach (\App\Enums\MediaType::cases() as $type)
                            @php $selected = old('extensions.'.$type->value, $extensions[$type->value]); @endphp
                            <fieldset class="mb-3">
                                <legend class="form-label fs-6"><i class="bi {{ $type->icon() }}" aria-hidden="true"></i> {{ $type->label() }}</legend>
                                <div class="d-flex flex-wrap gap-3">
                                    @foreach ($supported[$type->value] as $ext)
                                        <div class="form-check"><input class="form-check-input" type="checkbox" name="extensions[{{ $type->value }}][]" value="{{ $ext }}" id="ext-{{ $type->value }}-{{ $ext }}" @checked(in_array($ext, $selected))>
                                            <label class="form-check-label ltr-text" for="ext-{{ $type->value }}-{{ $ext }}">{{ strtoupper($ext) }}</label></div>
                                    @endforeach
                                </div>
                                @error('extensions.'.$type->value)<div class="text-danger small">{{ $message }}</div>@enderror
                            </fieldset>
                        @endforeach
                        <div class="form-text">{{ __('admin.settings.extensions_help') }}</div>
                    </div>
                </section>
            </div>

            <div class="col-12">
                <section class="aimv-card" aria-labelledby="c3">
                    <div class="card-head"><h2 id="c3">{{ __('admin.settings.general') }}</h2></div>
                    <div class="card-body-pad row g-3">
                        <div class="col-md-6"><x-form.input name="contact_email" type="email" :label="__('admin.settings.contact_email')" :value="$settings['contact_email']" required ltr /></div>
                        <div class="col-md-6 d-flex align-items-center">
                            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="registration_open" id="registration_open" value="1" @checked(old('registration_open', $settings['registration_open']))>
                                <label class="form-check-label" for="registration_open">{{ __('admin.settings.registration_open') }}</label></div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
        <button class="btn btn-primary btn-lg mt-4"><i class="bi bi-save" aria-hidden="true"></i> {{ __('common.save') }}</button>
    </form>

    <section class="aimv-card mt-4" aria-labelledby="c4">
        <div class="card-head"><h2 id="c4">{{ __('admin.settings.maintenance') }}</h2></div>
        <div class="card-body-pad d-flex flex-wrap gap-3 align-items-center">
            <div class="flex-grow-1"><strong>{{ __('admin.settings.expired', ['count' => $expiredCount]) }}</strong><div class="small text-muted">{{ __('admin.settings.purge_help') }}</div></div>
            <form method="POST" action="{{ route('admin.settings.purge') }}" onsubmit="return confirm(@js(__('admin.settings.purge_confirm')))">@csrf
                <button class="btn btn-outline-danger"><i class="bi bi-trash3" aria-hidden="true"></i> {{ __('admin.settings.purge') }}</button></form>
        </div>
    </section>
@endsection
