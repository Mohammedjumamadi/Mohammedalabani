@extends('layouts.public')

@section('title', __('reports.verify.title'))

@section('content')
<div class="container section" style="max-width:44rem">
    <h1>{{ __('reports.verify.title') }}</h1>
    <p class="lead text-muted">{{ __('reports.verify.intro') }}</p>

    <form method="POST" action="{{ route('reports.verify.check') }}" class="aimv-card card-body-pad mb-4" novalidate>
        @csrf
        <label for="reference" class="form-label">{{ __('verifications.show.reference') }}</label>
        <div class="input-group">
            <input id="reference" name="reference" type="text" dir="ltr" class="form-control @error('reference') is-invalid @enderror" value="{{ old('reference', $reference) }}" placeholder="AMV-2026-XXXXXXXX" maxlength="30" required>
            <button class="btn btn-primary"><i class="bi bi-search" aria-hidden="true"></i> {{ __('reports.verify.check') }}</button>
            @error('reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </form>

    @if ($searched)
        @if ($found)
            <section class="aimv-card" aria-live="polite">
                <div class="card-head">
                    <h2><i class="bi bi-patch-check-fill text-success" aria-hidden="true"></i> {{ __('reports.verify.found') }}</h2>
                </div>
                <dl class="card-body-pad mb-0">
                    <dt class="text-muted small">{{ __('verifications.show.reference') }}</dt><dd class="ltr-text fw-bold">{{ $found->reference_number }}</dd>
                    <dt class="text-muted small">{{ __('verifications.show.final_classification') }}</dt><dd><x-verdict-badge :value="$found->result->classification" /></dd>
                    <dt class="text-muted small">{{ __('verifications.show.file_type') }}</dt><dd>{{ $found->mediaFile->media_type->label() }}</dd>
                    <dt class="text-muted small">{{ __('verifications.show.completed_at') }}</dt><dd>{{ $found->result->completed_at?->translatedFormat('j F Y - H:i') }}</dd>
                    <dt class="text-muted small">{{ __('verifications.show.model_name') }}</dt><dd class="ltr-text">{{ $found->result->model_name }} v{{ $found->result->model_version }}</dd>
                    <dt class="text-muted small">{{ __('verifications.show.sha256') }}</dt><dd class="hash mb-0">{{ $found->mediaFile->sha256 }}</dd>
                </dl>
                <div class="card-body-pad border-top small text-muted">{{ __('reports.verify.privacy_note') }}</div>
            </section>
        @else
            <div class="alert alert-danger d-flex gap-2" role="alert">
                <i class="bi bi-x-octagon-fill" aria-hidden="true"></i> <div>{{ __('reports.verify.not_found') }}</div>
            </div>
        @endif
    @endif
</div>
@endsection
