@extends('layouts.panel')

@section('panel', 'admin')
@section('title', __('nav.admin.users'))

@section('content')
    <div class="page-head"><h1>{{ __('nav.admin.users') }}</h1></div>

    <form method="GET" class="aimv-card card-body-pad mb-4" role="search">
        <div class="row g-2 align-items-end">
            <div class="col-md-4"><label class="form-label small mb-1" for="q">{{ __('common.search') }}</label>
                <input id="q" name="q" type="search" class="form-control" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('admin.users.search_placeholder') }}"></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-1" for="status">{{ __('verifications.index.status') }}</label>
                <select id="status" name="status" class="form-select"><option value="">{{ __('common.all') }}</option>
                    @foreach (\App\Enums\UserStatus::cases() as $s)<option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>@endforeach</select></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-1" for="role">{{ __('admin.users.role') }}</label>
                <select id="role" name="role" class="form-select"><option value="">{{ __('common.all') }}</option>
                    <option value="admin" @selected(($filters['role'] ?? '') === 'admin')>{{ __('admin.users.role_admin') }}</option>
                    <option value="user" @selected(($filters['role'] ?? '') === 'user')>{{ __('admin.users.role_user') }}</option></select></div>
            <div class="col-6 col-md-2"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="deleted" value="1" id="deleted" @checked(($filters['deleted'] ?? '') === '1')><label class="form-check-label" for="deleted">{{ __('admin.users.show_deleted') }}</label></div></div>
            <div class="col-6 col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-funnel" aria-hidden="true"></i> {{ __('common.filter') }}</button></div>
        </div>
    </form>

    <div class="aimv-card">
        <div class="table-responsive">
            <table class="table table-aimv table-hover align-middle mb-0">
                <caption class="visually-hidden">{{ __('nav.admin.users') }}</caption>
                <thead><tr>
                    <th scope="col">{{ __('admin.users.name') }}</th><th scope="col">{{ __('admin.users.role') }}</th><th scope="col">{{ __('verifications.index.status') }}</th>
                    <th scope="col">{{ __('admin.users.verifications') }}</th><th scope="col">{{ __('admin.users.last_login') }}</th><th scope="col"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                </tr></thead>
                <tbody>
                    @forelse ($users as $u)
                        <tr>
                            <td><a class="fw-bold text-decoration-none" href="{{ route('admin.users.show', $u) }}">{{ $u->name }}</a>@if ($u->trashed()) <span class="verdict-badge verdict-fake">{{ __('admin.users.deleted_badge') }}</span>@endif
                                <div class="small text-muted ltr-text">{{ $u->email }}</div></td>
                            <td>{{ $u->role->label() }}</td>
                            <td><x-verdict-badge :value="$u->status" /></td>
                            <td>{{ $u->verification_requests_count }}</td>
                            <td class="small text-nowrap">{{ $u->last_login_at?->diffForHumans() ?? '—' }}</td>
                            <td class="text-end text-nowrap">
                                @if ($u->trashed())
                                    <form method="POST" action="{{ route('admin.users.restore', $u->id) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-primary">{{ __('admin.users.restore') }}</button></form>
                                @else
                                    <a href="{{ route('admin.users.show', $u) }}" class="btn btn-sm btn-outline-primary">{{ __('common.view') }}</a>
                                    @if (! $u->is(auth()->user()))
                                        <form method="POST" action="{{ route('admin.users.status', $u) }}" class="d-inline">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="status" value="{{ $u->isActive() ? 'suspended' : 'active' }}">
                                            <button class="btn btn-sm {{ $u->isActive() ? 'btn-outline-warning' : 'btn-outline-success' }}">{{ $u->isActive() ? __('admin.users.suspend') : __('admin.users.activate') }}</button>
                                        </form>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-5">{{ __('common.no_data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())<div class="card-body-pad border-top">{{ $users->links() }}</div>@endif
    </div>
@endsection
