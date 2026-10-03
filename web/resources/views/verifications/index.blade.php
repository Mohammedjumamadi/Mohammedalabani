@extends('layouts.panel')

@section('title', __('verifications.index.title'))

@section('content')
    <div class="dash-page-head">
        <div>
            <h1 class="dash-welcome">{{ __('verifications.index.title') }}</h1>
            <p class="text-muted mb-0 small">{{ __('dashboard.recent') }}</p>
        </div>
        <a href="{{ route('verifications.create') }}" class="btn btn-accent dash-new-btn">
            <i class="bi bi-cloud-arrow-up-fill" aria-hidden="true"></i> {{ __('dashboard.new_verification') }}
        </a>
    </div>

    {{-- Filter Toolbar --}}
    <form method="GET" class="aimv-card card-body-pad p-3 mb-4 shadow-sm" role="search" aria-label="{{ __('common.filter') }}">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label for="q" class="form-label small mb-1 fw-bold text-muted">{{ __('common.search') }}</label>
                <div class="input-group">
                    <span class="input-group-text bg-surface border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control border-start-0" placeholder="{{ __('verifications.index.search_placeholder') }}">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <label for="type" class="form-label small mb-1 fw-bold text-muted">{{ __('verifications.index.type') }}</label>
                <select id="type" name="type" class="form-select">
                    <option value="">{{ __('common.all') }}</option>
                    @foreach (\App\Enums\MediaType::cases() as $t)
                        <option value="{{ $t->value }}" @selected(($filters['type'] ?? '') === $t->value)>{{ $t->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="classification" class="form-label small mb-1 fw-bold text-muted">{{ __('verifications.index.classification') }}</label>
                <select id="classification" name="classification" class="form-select">
                    <option value="">{{ __('common.all') }}</option>
                    @foreach (\App\Enums\Classification::cases() as $c)
                        <option value="{{ $c->value }}" @selected(($filters['classification'] ?? '') === $c->value)>{{ $c->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="status" class="form-label small mb-1 fw-bold text-muted">{{ __('verifications.index.status') }}</label>
                <select id="status" name="status" class="form-select">
                    <option value="">{{ __('common.all') }}</option>
                    @foreach (\App\Enums\RequestStatus::cases() as $s)
                        <option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1 d-inline-flex align-items-center justify-content-center gap-1.5" type="submit">
                    <i class="bi bi-funnel-fill" aria-hidden="true"></i>
                    <span>{{ __('common.filter') }}</span>
                </button>
                @if (array_filter($filters))
                    <a href="{{ route('verifications.index') }}" class="btn btn-outline-secondary" aria-label="{{ __('verifications.index.clear') }}" title="{{ __('verifications.index.clear') }}">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </a>
                @endif
            </div>
        </div>
    </form>

    <div class="aimv-card shadow-sm overflow-hidden">
        @if ($requests->isEmpty())
            <div class="card-body-pad text-center py-5">
                <div class="p-3 bg-surface rounded-circle d-inline-block mb-3">
                    <i class="bi bi-inbox display-4 text-muted" aria-hidden="true"></i>
                </div>
                <h2 class="h5 text-primary mb-1">{{ __('verifications.index.empty') }}</h2>
                <p class="text-muted small mb-3">{{ __('pages.home.lead') }}</p>
                <a href="{{ route('verifications.create') }}" class="btn btn-primary">
                    <i class="bi bi-cloud-arrow-up-fill me-1"></i> {{ __('dashboard.new_verification') }}
                </a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-aimv table-hover align-middle mb-0">
                    <caption class="visually-hidden">{{ __('verifications.index.title') }}</caption>
                    <thead class="bg-surface">
                        <tr>
                            <th scope="col" class="py-3 px-4">{{ __('verifications.index.file') }}</th>
                            <th scope="col" class="py-3">{{ __('verifications.index.type') }}</th>
                            <th scope="col" class="py-3">{{ __('verifications.index.result') }}</th>
                            <th scope="col" class="py-3">{{ __('verifications.index.manipulation') }}</th>
                            <th scope="col" class="py-3">{{ __('verifications.index.date') }}</th>
                            <th scope="col" class="py-3 px-4 text-end"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requests as $item)
                            <tr class="dash-recent-row">
                                <td class="py-3 px-4">
                                    <div class="d-flex align-items-center gap-2">
                                        <a href="{{ route('verifications.show', $item) }}" class="fw-bold text-decoration-none text-break text-primary hover-text-accent">
                                            {{ \Illuminate\Support\Str::limit($item->mediaFile->original_name, 40) }}
                                        </a>
                                        @if ($item->is_demo)
                                            <span class="badge bg-warning-subtle text-warning border border-warning small">{{ __('common.demo_data') }}</span>
                                        @endif
                                    </div>
                                    <div class="small text-muted font-monospace ltr-text mt-0.5">#{{ $item->reference_number }}</div>
                                </td>
                                <td class="py-3">
                                    <span class="d-inline-flex align-items-center gap-1.5 text-nowrap">
                                        <i class="bi {{ $item->mediaFile->media_type->icon() }} text-accent" aria-hidden="true"></i>
                                        <span>{{ $item->mediaFile->media_type->label() }}</span>
                                    </span>
                                </td>
                                <td class="py-3">
                                    @if ($item->result)
                                        <x-verdict-badge :value="$item->result->classification" />
                                    @else
                                        <x-verdict-badge :value="$item->status" />
                                    @endif
                                </td>
                                <td class="py-3 ltr-text fw-bold text-nowrap">
                                    @if ($item->result)
                                        <span class="{{ $item->result->manipulation_probability >= 70 ? 'text-danger' : ($item->result->manipulation_probability <= 30 ? 'text-success' : 'text-warning') }}">
                                            {{ number_format($item->result->manipulation_probability, 1) }}%
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="py-3 text-nowrap small text-muted">{{ $item->created_at->translatedFormat('j M Y H:i') }}</td>
                                <td class="py-3 px-4 text-end text-nowrap">
                                    <a href="{{ route('verifications.show', $item) }}" class="btn btn-sm btn-outline-primary me-1" title="{{ __('common.view') }}">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </a>
                                    <form method="POST" action="{{ route('verifications.destroy', $item) }}" class="d-inline" onsubmit="return confirm(@js(__('common.confirm_delete')))">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="{{ __('common.delete') }}" title="{{ __('common.delete') }}">
                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($requests->hasPages())
                <div class="card-body-pad p-3 border-top bg-surface">{{ $requests->links() }}</div>
            @endif
        @endif
    </div>
@endsection
