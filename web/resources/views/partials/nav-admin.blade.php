@php
    $items = [
        ['route' => 'admin.dashboard', 'icon' => 'bi-speedometer2', 'label' => __('nav.admin.dashboard'), 'active' => ['admin.dashboard']],
        ['route' => 'admin.users.index', 'icon' => 'bi-people', 'label' => __('nav.admin.users'), 'active' => ['admin.users.*']],
        ['route' => 'admin.verifications.index', 'icon' => 'bi-shield-check', 'label' => __('nav.admin.verifications'), 'active' => ['admin.verifications.*']],
        ['route' => 'admin.statistics', 'icon' => 'bi-graph-up', 'label' => __('nav.admin.statistics'), 'active' => ['admin.statistics']],
        ['route' => 'admin.models.index', 'icon' => 'bi-cpu', 'label' => __('nav.admin.models'), 'active' => ['admin.models.*']],
        ['route' => 'admin.content.index', 'icon' => 'bi-journal-text', 'label' => __('nav.admin.content'), 'active' => ['admin.content.*']],
        ['route' => 'admin.settings.edit', 'icon' => 'bi-sliders', 'label' => __('nav.admin.settings'), 'active' => ['admin.settings.*']],
        ['route' => 'admin.activity.index', 'icon' => 'bi-list-check', 'label' => __('nav.admin.activity'), 'active' => ['admin.activity.*']],
    ];
@endphp

<nav class="d-grid gap-1" aria-label="{{ __('nav.menu') }}">
    @foreach ($items as $item)
        @if (Route::has($item['route']))
            <a class="side-link @if (request()->routeIs(...$item['active'])) active @endif"
               href="{{ route($item['route']) }}"
               @if (request()->routeIs(...$item['active'])) aria-current="page" @endif>
                <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>{{ $item['label'] }}
            </a>
        @endif
    @endforeach

    <div class="side-title">{{ __('nav.admin.site') }}</div>
    <a class="side-link" href="{{ route('home') }}"><i class="bi bi-house" aria-hidden="true"></i>{{ __('nav.home') }}</a>
</nav>
