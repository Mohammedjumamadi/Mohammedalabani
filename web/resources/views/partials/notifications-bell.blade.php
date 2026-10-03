@php
    $unreadCount = auth()->user()->userNotifications()->unread()->count();
    $latest = auth()->user()->userNotifications()->latest()->limit(5)->get();
@endphp

<div class="dropdown">
    <button class="btn btn-outline-primary btn-sm position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('notifications.title') }}@if ($unreadCount) ({{ $unreadCount }})@endif">
        <i class="bi bi-bell" aria-hidden="true"></i>
        @if ($unreadCount)
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" aria-hidden="true">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
        @endif
    </button>
    <div class="dropdown-menu dropdown-menu-end p-0" style="min-width:20rem;max-width:90vw">
        <div class="px-3 py-2 fw-bold border-bottom">{{ __('notifications.title') }}</div>
        @forelse ($latest as $item)
            <a class="dropdown-item d-block py-2 text-wrap {{ $item->isRead() ? '' : 'bg-surface' }}" href="{{ route('notifications.open', $item->id) }}">
                <div class="fw-bold small">{{ $item->title }}</div>
                <div class="small text-muted">{{ \Illuminate\Support\Str::limit($item->body, 70) }}</div>
            </a>
        @empty
            <div class="px-3 py-3 small text-muted">{{ __('notifications.empty') }}</div>
        @endforelse
        <a class="dropdown-item text-center border-top small py-2" href="{{ route('notifications.index') }}">{{ __('notifications.view_all') }}</a>
    </div>
</div>
