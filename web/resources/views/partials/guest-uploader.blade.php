@php
    $uploaderConfig = [
        'initialType' => 'image',
        'types' => $guestTypes,
        'maxBytes' => $guestMaxBytes,
        'maxLabel' => $guestMaxLabel,
        'messages' => [
            'extension' => __('upload.errors.extension', ['types' => ':types']),
            'tooLarge' => __('upload.errors.too_large', ['max' => ':max']),
            'required' => __('upload.errors.required'),
            'network' => __('verifications.create.network_error'),
            'throttle' => __('home_upload.throttled'),
            'generic' => __('errors.500.text'),
        ],
    ];
@endphp

{{-- نموذج الرفع الحقيقي للزائر (guest.verify): التحليل يتم فعليًا عبر خدمة الذكاء الاصطناعي --}}
<section class="site-section site-section-alt" id="try-now" aria-labelledby="try-now-title">
    <div class="container">
        <div class="row g-4 g-lg-5">
            <div class="col-lg-4">
                <h2 id="try-now-title" class="site-h2">{{ __('home_upload.title') }}</h2>
                <p class="site-lead">{{ __('home_upload.lead') }}</p>
                <p class="site-note mb-0">
                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                    <span>{{ __('home_upload.guest_notice') }}</span>
                </p>
            </div>

            <div class="col-lg-8">
                <form method="POST" action="{{ route('guest.verify') }}" enctype="multipart/form-data" class="case-sheet"
                      x-data="uploader(@js($uploaderConfig))" @submit="submit($event)" novalidate>
                    @csrf
                    <div class="case-sheet-tab">{{ __('landing.upload.case_label') }}</div>

                    {{-- 1. نوع الملف: كل خيار يعرض الامتدادات المقبولة له --}}
                    <fieldset class="case-step">
                        <legend class="case-step-title"><span class="case-step-num">1</span>{{ __('landing.upload.types_label') }}</legend>
                        <div class="type-options">
                            @foreach (\App\Enums\MediaType::cases() as $mediaType)
                                <input type="radio" class="btn-check" name="media_type" id="guest-type-{{ $mediaType->value }}" value="{{ $mediaType->value }}"
                                       x-model="type" @change="changeType()" @checked($mediaType === \App\Enums\MediaType::Image)>
                                <label class="type-option" for="guest-type-{{ $mediaType->value }}">
                                    <span class="type-option-name"><i class="bi {{ $mediaType->icon() }}" aria-hidden="true"></i> {{ $mediaType->label() }}</span>
                                    <span class="type-option-ext ltr-text">{{ strtoupper(implode(' · ', $guestTypes[$mediaType->value] ?? [])) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    {{-- 2. الملف --}}
                    <div class="case-step">
                        <div class="case-step-title"><span class="case-step-num">2</span>{{ __('landing.upload.file_label') }}</div>

                        <label for="guest-file" class="drop-area" x-show="!file" :class="{ 'is-over': dragging }"
                               @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="drop($event)">
                            <i class="bi bi-upload drop-icon" aria-hidden="true"></i>
                            <span class="drop-title">{{ __('verifications.create.drop_here') }}</span>
                            <span class="drop-meta">
                                <span class="ltr-text" x-text="extensions.join(', ').toUpperCase()"></span>
                                · {{ __('landing.upload.max', ['size' => $guestMaxLabel]) }}
                            </span>
                        </label>
                        <input class="visually-hidden" type="file" name="file" id="guest-file" x-ref="file" :accept="accept" @change="pick($event)" required>

                        <div class="picked-file" x-show="file" x-cloak>
                            <i class="bi bi-file-earmark-check" aria-hidden="true"></i>
                            <div class="flex-grow-1 min-w-0">
                                <div class="picked-name text-break" x-text="file?.name"></div>
                                <div class="picked-size ltr-text" x-text="fileSize"></div>
                            </div>
                            <button type="button" class="btn btn-sm btn-site-ghost" @click="clearFile()" :disabled="uploading">
                                {{ __('verifications.create.remove_file') }}
                            </button>
                        </div>

                        {{-- التقاط صورة مباشرة (الكاميرا على الهاتف، ومنتقي الملفات على الحاسوب)؛ الملف يُنقل إلى حقل الرفع نفسه --}}
                        <div class="mt-2" x-show="type === 'image' && !file" x-cloak>
                            <label for="guest-camera" class="btn btn-site-ghost btn-sm mb-0">
                                <i class="bi bi-camera me-1" aria-hidden="true"></i> {{ __('home_upload.capture') }}
                            </label>
                            <input class="visually-hidden" type="file" id="guest-camera" accept="image/jpeg,image/png,image/webp" capture="environment" @change="capture($event)">
                        </div>

                        <div class="site-alert is-error mt-3" role="alert" x-show="error" x-cloak>
                            <i class="bi bi-exclamation-circle" aria-hidden="true"></i> <span x-text="error"></span>
                        </div>
                        @error('file')<div class="site-alert is-error mt-3" role="alert"><i class="bi bi-exclamation-circle" aria-hidden="true"></i> {{ $message }}</div>@enderror
                        @error('media_type')<div class="site-alert is-error mt-3" role="alert"><i class="bi bi-exclamation-circle" aria-hidden="true"></i> {{ $message }}</div>@enderror
                    </div>

                    {{-- 3. الموافقة والإرسال --}}
                    <div class="case-step">
                        <div class="form-check mb-3">
                            <input class="form-check-input @error('consent') is-invalid @enderror" type="checkbox" name="consent" id="guest-consent" value="1" required @checked(old('consent'))>
                            <label class="form-check-label" for="guest-consent">{{ __('verifications.create.consent') }}</label>
                            @error('consent')<div class="invalid-feedback d-block"><i class="bi bi-exclamation-circle" aria-hidden="true"></i> {{ $message }}</div>@enderror
                        </div>

                        <div x-show="uploading" x-cloak class="mb-3" role="status" aria-live="polite">
                            <div class="d-flex justify-content-between small mb-1">
                                <span>{{ __('verifications.create.uploading') }}</span>
                                <span class="ltr-text" x-text="percent + '%'"></span>
                            </div>
                            <div class="site-progress" role="progressbar" :aria-valuenow="percent" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('verifications.create.uploading') }}">
                                <span :style="'width:' + percent + '%'"></span>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-site btn-lg w-100" :disabled="uploading">
                            <span class="spinner-border spinner-border-sm me-2" x-show="uploading" x-cloak aria-hidden="true"></span>
                            {{ __('home_upload.submit') }}
                        </button>
                        <p class="site-hint mt-3 mb-0">{{ __('home_upload.register_hint') }}</p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
