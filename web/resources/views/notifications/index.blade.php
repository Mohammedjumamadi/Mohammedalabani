@extends('layouts.panel')

@section('title', __('notifications.title'))

@section('content')
    <div class="page-head">
        <h1>{{ __('notifications.title') }}</h1>
        @if ($notifications->contains(fn ($n) => ! $n->isRead()))
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button class="btn btn-outline-primary"><i class="bi bi-check2-all" aria-hidden="true"></i> {{ __('notifications.mark_all') }}</button>
            </form>
        @endif
    </div>

    <div class="aimv-card">
        @forelse ($notifications as $notification)
            <a href="{{ route('notifications.open', $notification->id) }}" class="d-flex gap-3 card-body-pad text-decoration-none text-reset {{ $loop->last ? '' : 'border-bottom' }} {{ $notification->isRead() ? '' : 'bg-surface' }}">
                <i class="bi {{ str_contains($notification->type, 'failed') ? 'bi-x-octagon-fill text-danger' : 'bi-bell-fill text-accent' }} fs-4" aria-hidden="true"></i>
                <div class="flex-grow-1">
                    <div class="fw-bold">{{ $notification->title }} @unless ($notification->isRead())<span class="badge text-bg-primary">{{ __('notifications.new') }}</span>@endunless</div>
                    <div class="text-muted small">{{ $notification->body }}</div>
                    <div class="text-muted small">{{ $notification->created_at->diffForHumans() }}</div>
                </div>
            </a>
        @empty
            <div class="card-body-pad text-center text-muted py-5">{{ __('notifications.empty') }}</div>
        @endforelse
        @if ($notifications->hasPages())
            <div class="card-body-pad border-top">{{ $notifications->links() }}</div>
        @endif
    </div>
@endsection
