@php
    // كل عنصر يظهر فقط إذا كان مساره معرّفًا، فتُضاف الصفحات تدريجيًا دون تعديل هذا الملف
    $items = [
        ['route' => 'dashboard', 'icon' => 'bi-speedometer2', 'label' => __('nav.dashboard'), 'active' => ['dashboard']],
        ['route' => 'verifications.create', 'icon' => 'bi-cloud-arrow-up', 'label' => __('nav.upload'), 'active' => ['verifications.create']],
        ['route' => 'verifications.index', 'icon' => 'bi-clock-history', 'label' => __('nav.history'), 'active' => ['verifications.index', 'verifications.show', 'verifications.progress']],
        ['route' => 'profile.edit', 'icon' => 'bi-person', 'label' => __('nav.profile'), 'active' => ['profile.*']],
        ['route' => 'settings.edit', 'icon' => 'bi-gear', 'label' => __('nav.settings'), 'active' => ['settings.*']],
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

    <div class="side-title">{{ __('nav.learn') }}</div>
    @foreach ([
        ['pages.awareness', 'bi-mortarboard', __('nav.awareness')],
        ['pages.faq', 'bi-question-circle', __('nav.faq')],
    ] as [$route, $icon, $label])
        @if (Route::has($route))
            <a class="side-link" href="{{ route($route) }}"><i class="bi {{ $icon }}" aria-hidden="true"></i>{{ $label }}</a>
        @endif
    @endforeach
</nav>
