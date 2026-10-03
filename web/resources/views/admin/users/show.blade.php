@extends('layouts.panel')

@section('panel', 'admin')
@section('title', $user->name)

@section('content')
    <div class="page-head">
        <div><h1>{{ $user->name }}</h1><div class="text-muted small ltr-text">{{ $user->email }}</div></div>
        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-primary">{{ __('common.back') }}</a>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <section class="aimv-card mb-4" aria-labelledby="ui">
                <div class="card-head"><h2 id="ui">{{ __('admin.users.details') }}</h2><x-verdict-badge :value="$user->status" /></div>
                <dl class="card-body-pad mb-0 small">
                    <dt class="text-muted">{{ __('admin.users.role') }}</dt><dd>{{ $user->role->label() }}</dd>
                    <dt class="text-muted">{{ __('validation.attributes.account_type') }}</dt><dd>{{ $user->account_type ? __('enums.account_type.'.$user->account_type) : '—' }}</dd>
                    <dt class="text-muted">{{ __('validation.attributes.organization') }}</dt><dd>{{ $user->organization ?: '—' }}</dd>
                    <dt class="text-muted">{{ __('validation.attributes.phone') }}</dt><dd class="ltr-text">{{ $user->phone ?: '—' }}</dd>
                    <dt class="text-muted">{{ __('profile.member_since') }}</dt><dd>{{ $user->created_at->translatedFormat('j F Y') }}</dd>
                    <dt class="text-muted">{{ __('admin.users.last_login') }}</dt><dd>{{ $user->last_login_at?->translatedFormat('j F Y - H:i') ?? '—' }}</dd>
                    <dt class="text-muted">{{ __('admin.users.verifications') }}</dt><dd class="mb-0">{{ $user->verification_requests_count }}</dd>
                </dl>
            </section>

            @if (! $user->trashed() && ! $user->is(auth()->user()))
                <section class="aimv-card" aria-labelledby="ua">
                    <div class="card-head"><h2 id="ua">{{ __('admin.users.manage') }}</h2></div>
                    <div class="card-body-pad d-grid gap-2">
                        <form method="POST" action="{{ route('admin.users.status', $user) }}">@csrf @method('PUT')
                            <input type="hidden" name="status" value="{{ $user->isActive() ? 'suspended' : 'active' }}">
                            <button class="btn w-100 {{ $user->isActive() ? 'btn-outline-warning' : 'btn-outline-success' }}">{{ $user->isActive() ? __('admin.users.suspend') : __('admin.users.activate') }}</button></form>
                        <form method="POST" action="{{ route('admin.users.role', $user) }}">@csrf @method('PUT')
                            <input type="hidden" name="role" value="{{ $user->isAdmin() ? 'user' : 'admin' }}">
                            <button class="btn btn-outline-primary w-100" onclick="return confirm(@js(__('admin.users.confirm_role')))">{{ $user->isAdmin() ? __('admin.users.make_user') : __('admin.users.make_admin') }}</button></form>
                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm(@js(__('admin.users.confirm_delete')))">@csrf @method('DELETE')
                            <button class="btn btn-outline-danger w-100"><i class="bi bi-trash" aria-hidden="true"></i> {{ __('common.delete') }}</button></form>
                    </div>
                </section>
            @endif
        </div>

        <div class="col-lg-8">
            <section class="aimv-card mb-4" aria-labelledby="ur">
                <div class="card-head"><h2 id="ur">{{ __('admin.users.recent') }}</h2></div>
                <div class="table-responsive"><table class="table table-aimv table-hover align-middle mb-0">
                    <caption class="visually-hidden">{{ __('admin.users.recent') }}</caption>
                    <thead><tr><th scope="col">{{ __('verifications.index.file') }}</th><th scope="col">{{ __('verifications.index.result') }}</th><th scope="col">{{ __('verifications.index.date') }}</th></tr></thead>
                    <tbody>
                        @forelse ($recent as $item)
                            <tr>
                                <td><a href="{{ route('verifications.show', $item) }}" class="text-decoration-none fw-bold">{{ \Illuminate\Support\Str::limit($item->mediaFile->original_name, 30) }}</a><div class="small text-muted ltr-text">{{ $item->reference_number }}</div></td>
                                <td><x-verdict-badge :value="$item->result?->classification ?? $item->status" /></td>
                                <td class="small text-nowrap">{{ $item->created_at->translatedFormat('j M Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-4">{{ __('common.no_data') }}</td></tr>
                        @endforelse
                    </tbody></table></div>
            </section>

            <section class="aimv-card" aria-labelledby="uact">
                <div class="card-head"><h2 id="uact">{{ __('admin.dashboard.activity') }}</h2></div>
                <ul class="list-group list-group-flush">
                    @forelse ($activities as $log)
                        <li class="list-group-item d-flex justify-content-between gap-2 small"><span>{{ $log->description }} <span class="ltr-text text-muted">({{ $log->action }})</span></span><span class="text-muted text-nowrap">{{ $log->created_at->diffForHumans() }}</span></li>
                    @empty
                        <li class="list-group-item text-muted">{{ __('common.no_data') }}</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
@endsection
