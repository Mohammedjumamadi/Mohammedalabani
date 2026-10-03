@extends('layouts.panel')

@section('title', __('nav.dashboard'))

@php
    use App\Enums\MediaType;

    $isNew = $stats['total'] === 0;
    $classified = max(1, $stats['real'] + $stats['uncertain'] + $stats['fake']);
    $typeTotal = max(1, array_sum($typeChart['counts']));
    $typeIcons = collect(MediaType::cases())->map->icon()->all();
    $hasMonthly = array_sum($monthly['counts'] ?? []) > 0;
@endphp

@section('content')
    <header class="dash-head">
        <div class="min-w-0">
            <h1 class="dash-title">{{ __('dashboard.welcome', ['name' => $user->name]) }}</h1>
            <p class="dash-sub">{{ __('dashboard.subtitle') }}</p>
        </div>
        @unless ($isNew)
            <a href="{{ route('verifications.create') }}" class="btn btn-site">
                <i class="bi bi-upload me-2" aria-hidden="true"></i>{{ __('dashboard.new_verification') }}
            </a>
        @endunless
    </header>

    @if ($hasDemo)
        <div class="site-alert is-warn mb-4"><i class="bi bi-info-circle" aria-hidden="true"></i><span>{{ __('dashboard.demo_notice') }}</span></div>
    @endif

    @if ($isNew)
        {{-- حساب جديد: خطوة واحدة واضحة بدل أرقام صفرية ورسوم فارغة --}}
        <section class="dash-first" aria-labelledby="first-title">
            <div class="dash-first-body">
                <h2 id="first-title" class="dash-first-title">{{ __('dashboard.first_title') }}</h2>
                <p class="dash-first-text">{{ __('dashboard.first_text') }}</p>
                <ol class="dash-first-steps">
                    @foreach (__('dashboard.first_steps') as $i => $step)
                        <li><span class="hero-flow-num">{{ $i + 1 }}</span>{{ $step }}</li>
                    @endforeach
                </ol>
                <a href="{{ route('verifications.create') }}" class="btn btn-site btn-lg">
                    <i class="bi bi-upload me-2" aria-hidden="true"></i>{{ __('landing.hero.cta_try') }}
                </a>
            </div>
            <div class="dash-first-types" aria-hidden="true">
                @foreach (MediaType::cases() as $type)
                    <div class="dash-first-type"><i class="bi {{ $type->icon() }}"></i><span>{{ $type->label() }}</span></div>
                @endforeach
            </div>
        </section>
    @else
        <div class="row g-4">
            <div class="col-xl-8">
                {{-- ملخص النتائج في شريط واحد بدل خمس بطاقات --}}
                <section class="dash-panel" aria-labelledby="summary-title">
                    <div class="dash-panel-head">
                        <h2 id="summary-title" class="dash-panel-title">{{ __('dashboard.summary') }}</h2>
                        <span class="dash-panel-note">{{ __('dashboard.summary_note') }}</span>
                    </div>
                    <div class="dash-summary">
                        <div class="dash-stat is-total">
                            <span class="dash-stat-num">{{ number_format($stats['total']) }}</span>
                            <span class="dash-stat-label">{{ __('dashboard.total') }}</span>
                        </div>
                        @foreach (['real', 'uncertain', 'fake', 'failed'] as $key)
                            <div class="dash-stat is-{{ $key }}">
                                <span class="dash-stat-num"><span class="dash-dot" aria-hidden="true"></span>{{ number_format($stats[$key]) }}</span>
                                <span class="dash-stat-label">{{ __('dashboard.'.$key) }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="dash-ratio" aria-hidden="true">
                        @foreach (['real', 'uncertain', 'fake'] as $key)
                            @if ($stats[$key] > 0)
                                <span class="is-{{ $key }}" style="flex-grow: {{ $stats[$key] }}"></span>
                            @endif
                        @endforeach
                        @if ($stats['real'] + $stats['uncertain'] + $stats['fake'] === 0)
                            <span class="is-empty" style="flex-grow: 1"></span>
                        @endif
                    </div>
                </section>

                {{-- أحدث عمليات التحقق --}}
                <section class="dash-panel mt-4" aria-labelledby="recent-title">
                    <div class="dash-panel-head">
                        <h2 id="recent-title" class="dash-panel-title">{{ __('dashboard.recent') }}</h2>
                        <a href="{{ route('verifications.index') }}" class="dash-link">{{ __('dashboard.view_all') }}</a>
                    </div>
                    <ul class="dash-list">
                        @foreach ($recent as $item)
                            <li>
                                <a class="dash-row" href="{{ route('verifications.show', $item) }}">
                                    <span class="dash-row-icon" aria-hidden="true"><i class="bi {{ $item->mediaFile->media_type->icon() }}"></i></span>
                                    <span class="dash-row-main">
                                        <span class="dash-row-name">{{ \Illuminate\Support\Str::limit($item->mediaFile->original_name, 48) }}</span>
                                        <span class="dash-row-meta">
                                            {{ $item->mediaFile->media_type->label() }} ·
                                            <span class="ltr-text">{{ $item->reference_number }}</span> ·
                                            {{ $item->created_at->translatedFormat('j M Y') }}
                                        </span>
                                    </span>
                                    <span class="dash-row-end">
                                        @if ($item->result)
                                            <span class="sev is-{{ ['real' => 'low', 'uncertain' => 'medium', 'fake' => 'high'][$item->result->classification->value] }}">{{ $item->result->classification->label() }}</span>
                                            <span class="dash-row-conf">{{ __('dashboard.confidence') }} <span class="ltr-text">{{ number_format($item->result->confidence_score, 0) }}%</span></span>
                                        @else
                                            <span @class(['sev', 'is-high' => $item->status->value === 'failed', 'is-info' => $item->status->value !== 'failed'])>{{ $item->status->label() }}</span>
                                        @endif
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            </div>

            <div class="col-xl-4">
                <section class="dash-panel" aria-labelledby="monthly-title">
                    <div class="dash-panel-head">
                        <h2 id="monthly-title" class="dash-panel-title">{{ __('dashboard.monthly') }}</h2>
                    </div>
                    <div class="dash-panel-body">
                        @if ($hasMonthly)
                            <div class="dash-chart"><canvas id="monthlyChart" role="img" aria-label="{{ __('dashboard.monthly') }}"></canvas></div>
                        @else
                            <p class="case-block-empty">{{ __('common.no_data') }}</p>
                        @endif
                    </div>
                </section>

                <section class="dash-panel mt-4" aria-labelledby="type-title">
                    <div class="dash-panel-head">
                        <h2 id="type-title" class="dash-panel-title">{{ __('dashboard.by_type') }}</h2>
                    </div>
                    <ul class="dash-types">
                        @foreach ($typeChart['labels'] as $i => $label)
                            <li>
                                <div class="dash-types-head">
                                    <span><i class="bi {{ $typeIcons[$i] }}" aria-hidden="true"></i> {{ $label }}</span>
                                    <strong>{{ $typeChart['counts'][$i] }}</strong>
                                </div>
                                <div class="case-bar-track"><span style="width: {{ round($typeChart['counts'][$i] / $typeTotal * 100) }}%; background: var(--s-accent)"></span></div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            </div>
        </div>
    @endif
@endsection

@if (! $isNew && $hasMonthly)
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const el = document.getElementById('monthlyChart');
        if (!el || !window.Chart) return;
        Chart.defaults.font = { family: 'Cairo', size: 12 };
        Chart.defaults.color = '#5d6d80';
        new Chart(el, {
            type: 'bar',
            data: {
                labels: @js($monthly['labels']),
                datasets: [{
                    label: @js(__('dashboard.total')),
                    data: @js($monthly['counts']),
                    backgroundColor: 'rgba(47, 109, 176, .55)',
                    hoverBackgroundColor: '#2f6db0',
                    borderRadius: 4,
                    maxBarThickness: 28,
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(27, 42, 58, .07)' }, border: { display: false } },
                    x: { grid: { display: false }, border: { color: 'rgba(27, 42, 58, .15)' } }
                }
            }
        });
    });
    </script>
    @endpush
@endif
