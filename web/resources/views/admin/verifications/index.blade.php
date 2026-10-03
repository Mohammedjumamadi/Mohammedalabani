@extends('layouts.panel')

@section('panel', 'admin')
@section('title', __('nav.admin.verifications'))

@section('content')
    <div class="page-head">
        <h1>{{ __('nav.admin.verifications') }}</h1>
        @if ($failedCount)
            <a href="{{ route('admin.verifications.index', ['status' => 'failed']) }}" class="btn btn-outline-danger"><i class="bi bi-slash-circle" aria-hidden="true"></i> {{ __('admin.verifications.failed_files', ['count' => $failedCount]) }}</a>
        @endif
    </div>

    <form method="GET" class="aimv-card card-body-pad mb-4" role="search">
        <div class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label small mb-1" for="q">{{ __('common.search') }}</label>
                <input id="q" name="q" type="search" class="form-control" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('admin.verifications.search_placeholder') }}"></div>
            @foreach ([['type', 'verifications.index.type', \App\Enums\MediaType::cases()], ['classification', 'verifications.index.classification', \App\Enums\Classification::cases()], ['status', 'verifications.index.status', \App\Enums\RequestStatus::cases()]] as [$name, $label, $cases])
                <div class="col-6 col-md-2"><label class="form-label small mb-1" for="{{ $name }}">{{ __($label) }}</label>
                    <select id="{{ $name }}" name="{{ $name }}" class="form-select"><option value="">{{ __('common.all') }}</option>
                        @foreach ($cases as $c)<option value="{{ $c->value }}" @selected(($filters[$name] ?? '') === $c->value)>{{ $c->label() }}</option>@endforeach</select></div>
            @endforeach
            <div class="col-6 col-md-2"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="demo" value="0" id="demo" @checked(($filters['demo'] ?? '') === '0')><label class="form-check-label small" for="demo">{{ __('admin.verifications.hide_demo') }}</label></div></div>
            <div class="col-12 col-md-1"><button class="btn btn-primary w-100" aria-label="{{ __('common.filter') }}"><i class="bi bi-funnel" aria-hidden="true"></i></button></div>
        </div>
    </form>

    <div class="aimv-card">
        <div class="table-responsive">
            <table class="table table-aimv table-hover align-middle mb-0">
                <caption class="visually-hidden">{{ __('nav.admin.verifications') }}</caption>
                <thead><tr>
                    <th scope="col">{{ __('verifications.index.file') }}</th><th scope="col">{{ __('admin.activity.user') }}</th><th scope="col">{{ __('verifications.index.type') }}</th>
                    <th scope="col">{{ __('verifications.index.result') }}</th><th scope="col">{{ __('verifications.index.manipulation') }}</th><th scope="col">{{ __('verifications.index.date') }}</th><th scope="col"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                </tr></thead>
                <tbody>
                    @forelse ($requests as $r)
                        <tr>
                            <td><a class="fw-bold text-decoration-none text-break" href="{{ route('verifications.show', $r) }}">{{ \Illuminate\Support\Str::limit($r->mediaFile->original_name, 28) }}</a>
                                <div class="small text-muted ltr-text">{{ $r->reference_number }}</div>@if ($r->is_demo)<span class="demo-banner">{{ __('common.demo_data') }}</span>@endif</td>
                            <td class="small ltr-text">{{ $r->user?->email }}</td>
                            <td><i class="bi {{ $r->mediaFile->media_type->icon() }} text-accent" aria-hidden="true"></i> {{ $r->mediaFile->media_type->label() }}</td>
                            <td><x-verdict-badge :value="$r->result?->classification ?? $r->status" />@if ($r->status === \App\Enums\RequestStatus::Failed && $r->error_message)<div class="small text-muted">{{ __('ai_errors.'.$r->error_message) }}</div>@endif</td>
                            <td class="ltr-text">{{ $r->result ? number_format($r->result->manipulation_probability, 1).'%' : '—' }}</td>
                            <td class="small text-nowrap">{{ $r->created_at->translatedFormat('j M Y H:i') }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('verifications.show', $r) }}" class="btn btn-sm btn-outline-primary">{{ __('common.view') }}</a>
                                <form method="POST" action="{{ route('verifications.destroy', $r) }}" class="d-inline" onsubmit="return confirm(@js(__('admin.verifications.confirm_takedown')))">
                                    @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" aria-label="{{ __('admin.verifications.takedown') }}" title="{{ __('admin.verifications.takedown') }}"><i class="bi bi-trash" aria-hidden="true"></i></button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-5">{{ __('common.no_data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($requests->hasPages())<div class="card-body-pad border-top">{{ $requests->links() }}</div>@endif
    </div>
@endsection
