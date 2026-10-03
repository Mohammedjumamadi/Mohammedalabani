@extends('layouts.panel')

@section('title', __('profile.title'))

@section('content')
    <div class="page-head">
        <h1>{{ __('profile.title') }}</h1>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <section class="aimv-card" aria-labelledby="profile-info">
                <div class="card-head"><h2 id="profile-info">{{ __('profile.info') }}</h2></div>
                <form method="POST" action="{{ route('profile.update') }}" class="card-body-pad" novalidate>
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-form.input name="name" :label="__('validation.attributes.name')" :value="$user->name" required autocomplete="name" />
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email_ro" class="form-label">{{ __('validation.attributes.email') }}</label>
                                <input id="email_ro" type="email" class="form-control" value="{{ $user->email }}" dir="ltr" style="text-align:start" disabled aria-describedby="email-hint">
                                <div class="form-text" id="email-hint">{{ __('profile.email_readonly') }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <x-form.input name="phone" type="tel" :label="__('validation.attributes.phone')" :value="$user->phone" autocomplete="tel" ltr />
                        </div>
                        <div class="col-md-6">
                            <x-form.input name="city" :label="__('validation.attributes.city')" :value="$user->city" autocomplete="address-level2" maxlength="100" />
                        </div>
                        <div class="col-md-6">
                            <x-form.input name="organization" :label="__('validation.attributes.organization')" :value="$user->organization" autocomplete="organization" />
                        </div>
                        <div class="col-md-6">
                            <x-form.select
                                name="account_type"
                                :label="__('validation.attributes.account_type')"
                                :value="$user->account_type"
                                :options="collect(config('aimv.account_types'))->mapWithKeys(fn ($t) => [$t => __('enums.account_type.'.$t)])->all()"
                                required />
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">{{ __('common.save') }}</button>
                </form>
            </section>
        </div>

        <div class="col-lg-4">
            <section class="aimv-card" aria-labelledby="account-summary">
                <div class="card-head"><h2 id="account-summary">{{ __('profile.account') }}</h2></div>
                <dl class="card-body-pad mb-0">
                    <dt class="text-muted small">{{ __('profile.role') }}</dt>
                    <dd>{{ $user->role->label() }}</dd>
                    <dt class="text-muted small">{{ __('profile.member_since') }}</dt>
                    <dd>{{ $user->created_at->translatedFormat('j F Y') }}</dd>
                    <dt class="text-muted small">{{ __('profile.last_login') }}</dt>
                    <dd class="mb-0">{{ $user->last_login_at?->translatedFormat('j F Y - H:i') ?? '—' }}</dd>
                </dl>
            </section>
        </div>
    </div>
@endsection
