@extends('layouts.panel')

@section('title', __('verifications.create.title'))

@php
    $uploaderConfig = [
        'initialType' => old('media_type', 'image'),
        'types' => $types,
        'maxBytes' => $maxBytes,
        'maxLabel' => $maxLabel,
        'messages' => [
            'extension' => __('upload.errors.extension', ['types' => ':types']),
            'tooLarge' => __('upload.errors.too_large', ['max' => ':max']),
            'required' => __('upload.errors.required'),
            'network' => __('verifications.create.network_error'),
            'throttle' => __('errors.429.text'),
            'generic' => __('errors.500.text'),
        ],
    ];
@endphp

@push('head')
<style>
/* ==========================================================================
   Verification Upload Self-Contained Styles
   ========================================================================== */
.step-num-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.6rem;
    height: 1.6rem;
    background: var(--aimv-primary, #0b2a5b);
    color: #fff;
    border-radius: 50%;
    font-size: 0.75rem;
    font-weight: 700;
}

.type-tile-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 1.25rem 0.75rem;
    cursor: pointer;
    border: 2px solid var(--aimv-border, #dde3ee);
    border-radius: var(--aimv-radius, 0.875rem);
    background: #fff;
    height: 100%;
    transition: all 0.2s ease;
    text-align: center;
}
.type-tile input:checked + .type-tile-card {
    border-color: var(--aimv-primary, #0b2a5b);
    background: var(--aimv-primary-50, #eef3fb);
    box-shadow: 0 4px 16px rgba(11, 42, 91, 0.1);
    transform: translateY(-2px);
}
.type-tile-icon-box {
    font-size: 1.8rem;
    color: var(--aimv-accent, #0e7490);
    line-height: 1;
}
.type-tile input:checked + .type-tile-card .type-tile-icon-box {
    color: var(--aimv-primary, #0b2a5b);
}
.type-tile-title {
    font-weight: 700;
    font-size: 0.9rem;
    color: var(--aimv-primary, #0b2a5b);
}

.modern-dropzone {
    border: 2px dashed var(--aimv-border, #dde3ee);
    border-radius: var(--aimv-radius, 0.875rem);
    padding: 3rem 1.5rem;
    text-align: center;
    background: var(--aimv-surface, #f6f8fc);
    cursor: pointer;
    transition: all 0.2s ease;
}
.modern-dropzone:hover, .modern-dropzone.is-over {
    border-color: var(--aimv-accent, #0e7490);
    background: var(--aimv-accent-50, #ecfeff);
    transform: scale(1.005);
}
.dz-icon-wrap {
    width: 4rem;
    height: 4rem;
    background: #fff;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    box-shadow: 0 4px 14px rgba(14, 116, 144, 0.12);
}
.dz-icon-wrap .dz-icon {
    font-size: 2rem;
    color: var(--aimv-accent, #0e7490);
}
.selected-file-icon {
    line-height: 1;
}
</style>
@endpush

@section('content')
    <div class="dash-page-head">
        <div>
            <h1 class="dash-welcome">{{ __('verifications.create.title') }}</h1>
            <p class="text-muted mb-0 small">{{ __('pages.home.lead') }}</p>
        </div>
        @if (Route::has('verifications.index'))
            <a href="{{ route('verifications.index') }}" class="btn btn-outline-primary">
                <i class="bi bi-clock-history me-1" aria-hidden="true"></i> {{ __('nav.history') }}
            </a>
        @endif
    </div>

    @unless ($aiOnline)
        <div class="alert alert-warning d-flex align-items-center gap-3 mb-4 rounded-3 shadow-sm" role="status">
            <i class="bi bi-exclamation-triangle-fill fs-4 text-warning" aria-hidden="true"></i>
            <div>{{ __('verifications.create.ai_offline') }}</div>
        </div>
    @endunless

    <div class="row g-4">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('verifications.store') }}" enctype="multipart/form-data" class="aimv-card verification-form-card"
                  x-data="uploader(@js($uploaderConfig))" @submit="submit($event)" novalidate>
                @csrf

                {{-- Step 1: Media Type --}}
                <div class="card-head py-3 px-4 bg-surface border-bottom">
                    <h2 class="h6 mb-0 d-flex align-items-center gap-2 text-primary">
                        <span class="step-num-badge">1</span>
                        <span>{{ __('verifications.create.step_type') }}</span>
                    </h2>
                </div>
                <div class="card-body-pad p-4">
                    <fieldset>
                        <legend class="visually-hidden">{{ __('verifications.create.step_type') }}</legend>
                        <div class="row g-3">
                            @foreach (\App\Enums\MediaType::cases() as $mediaType)
                                <div class="col-4 type-tile position-relative">
                                    <input type="radio" name="media_type" id="type-{{ $mediaType->value }}" value="{{ $mediaType->value }}"
                                           x-model="type" @change="changeType()" @checked(old('media_type', 'image') === $mediaType->value)>
                                    <label for="type-{{ $mediaType->value }}" class="type-tile-card">
                                        <div class="type-tile-icon-box">
                                            <i class="bi {{ $mediaType->icon() }}" aria-hidden="true"></i>
                                        </div>
                                        <span class="type-tile-title">{{ $mediaType->label() }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </fieldset>
                </div>

                {{-- Step 2: File Dropzone --}}
                <div class="card-head py-3 px-4 bg-surface border-top border-bottom">
                    <h2 class="h6 mb-0 d-flex align-items-center gap-2 text-primary">
                        <span class="step-num-badge">2</span>
                        <span>{{ __('verifications.create.step_file') }}</span>
                    </h2>
                </div>
                <div class="card-body-pad p-4">
                    <label for="file" class="dropzone modern-dropzone d-block mb-0" :class="{ 'is-over': dragging }"
                           @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="drop($event)">
                        <div class="dz-icon-wrap mb-3">
                            <i class="bi bi-cloud-arrow-up dz-icon" aria-hidden="true"></i>
                        </div>
                        <div class="fw-bold fs-5 mb-1 text-primary">{{ __('verifications.create.drop_here') }}</div>
                        <div class="text-muted small">
                            {{ __('verifications.create.formats') }}
                            <span class="badge bg-light text-primary border ltr-text mx-1" x-text="extensions.join(', ').toUpperCase()"></span>
                            · {{ __('verifications.create.max_size', ['size' => $maxLabel]) }}
                        </div>
                    </label>
                    <input class="visually-hidden" type="file" name="file" id="file" x-ref="file" :accept="accept" @change="pick($event)" required>

                    {{-- Selected file pill: x-show على غلاف منفصل لأن d-flex (!important) يمنع الإخفاء --}}
                    <div x-show="file" x-cloak>
                    <div class="mt-3 p-3 bg-surface rounded-3 border d-flex align-items-center gap-3 flex-wrap">
                        <div class="selected-file-icon">
                            <i class="bi bi-file-earmark-check-fill text-success fs-3" aria-hidden="true"></i>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-bold text-break text-primary" x-text="file?.name"></div>
                            <span class="text-muted small ltr-text" x-text="fileSize"></span>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger" @click="clearFile()">
                            <i class="bi bi-x-circle me-1" aria-hidden="true"></i>
                            {{ __('verifications.create.remove_file') }}
                        </button>
                    </div>
                    </div>

                    <div class="text-danger mt-2" role="alert" x-show="error" x-cloak>
                        <i class="bi bi-x-octagon-fill me-1" aria-hidden="true"></i> <span x-text="error"></span>
                    </div>
                    @error('file')
                        <div class="text-danger mt-2" role="alert"><i class="bi bi-x-octagon-fill me-1" aria-hidden="true"></i> {{ $message }}</div>
                    @enderror
                    @error('media_type')
                        <div class="text-danger mt-2" role="alert">{{ $message }}</div>
                    @enderror

                    @if ($serverLimited)
                        <div class="form-text mt-2 text-muted small"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>{{ __('verifications.create.server_limited', ['size' => $maxLabel]) }}</div>
                    @endif
                </div>

                {{-- Step 3: Consent & Confirmation --}}
                <div class="card-head py-3 px-4 bg-surface border-top border-bottom">
                    <h2 class="h6 mb-0 d-flex align-items-center gap-2 text-primary">
                        <span class="step-num-badge">3</span>
                        <span>{{ __('verifications.create.step_consent') }}</span>
                    </h2>
                </div>
                <div class="card-body-pad p-4">
                    <div class="form-check mb-3">
                        <input class="form-check-input @error('consent') is-invalid @enderror" type="checkbox" name="consent" id="consent" value="1" required @checked(old('consent'))>
                        <label class="form-check-label fw-semibold" for="consent">{{ __('verifications.create.consent') }}</label>
                        @error('consent')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="reanalyze" id="reanalyze" value="1" @checked(old('reanalyze'))>
                        <label class="form-check-label" for="reanalyze">{{ __('verifications.create.reanalyze') }}</label>
                        <div class="form-text text-muted small">{{ __('verifications.create.reanalyze_help') }}</div>
                    </div>

                    {{-- Uploading Progress Bar --}}
                    <div x-show="uploading" x-cloak class="mb-4 p-3 bg-surface rounded-3 border" role="status" aria-live="polite">
                        <div class="d-flex justify-content-between small fw-bold mb-2">
                            <span class="d-flex align-items-center gap-2">
                                <span class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></span>
                                {{ __('verifications.create.uploading') }}
                            </span>
                            <span class="ltr-text text-primary" x-text="percent + '%'"></span>
                        </div>
                        <div class="progress" style="height: 0.65rem;" role="progressbar" :aria-valuenow="percent" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('verifications.create.uploading') }}">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-accent" :style="'width:' + percent + '%'"></div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-accent btn-lg px-4 py-2" :disabled="uploading">
                        <span class="spinner-border spinner-border-sm me-2" x-show="uploading" x-cloak aria-hidden="true"></span>
                        <i class="bi bi-shield-check me-2" x-show="!uploading" aria-hidden="true"></i>
                        {{ __('verifications.create.submit') }}
                    </button>
                </div>
            </form>
        </div>

        <div class="col-lg-4">
            <aside class="aimv-card mb-4" aria-labelledby="tips-title">
                <div class="card-head py-3 px-4 bg-surface border-bottom">
                    <h2 id="tips-title" class="h6 mb-0 text-primary">
                        <i class="bi bi-lightbulb-fill text-warning me-2" aria-hidden="true"></i>
                        {{ __('verifications.create.tips_title') }}
                    </h2>
                </div>
                <div class="card-body-pad p-4">
                    <ul class="list-unstyled mb-0 d-grid gap-3">
                        @foreach (['tip_1', 'tip_2', 'tip_3', 'tip_4'] as $tip)
                            <li class="d-flex align-items-start gap-2 small">
                                <i class="bi bi-check-circle-fill text-accent flex-shrink-0 mt-1" aria-hidden="true"></i>
                                <span>{{ __('verifications.create.'.$tip) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </aside>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Quick tip hover effect
    document.querySelectorAll('.type-tile-card').forEach(tile => {
        tile.addEventListener('click', function () {
            this.classList.add('animate-pulse');
            setTimeout(() => this.classList.remove('animate-pulse'), 300);
        });
    });
});
</script>
@endpush
