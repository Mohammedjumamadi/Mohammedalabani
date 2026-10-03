@php $pct = fn ($v) => number_format(($v ?? 0) * 100, 1).'%'; @endphp

@foreach ($byDataset as $key => $m)
    @php $cm = $m['confusion_matrix'] ?? ['tp' => 0, 'fp' => 0, 'tn' => 0, 'fn' => 0]; @endphp
    <div class="{{ $loop->last ? '' : 'mb-4 pb-4 border-bottom' }}">
        <h3 class="h6 mb-2">{{ $m['dataset_label'] ?? $key }}</h3>
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="row g-2 text-center">
                    @foreach ([['accuracy', 'pages.about.accuracy'], ['precision', 'pages.about.precision'], ['recall', 'pages.about.recall'], ['f1', 'pages.about.f1'], ['false_positive_rate', 'admin.models.fpr'], ['false_negative_rate', 'admin.models.fnr']] as [$mk, $label])
                        <div class="col-4"><div class="border rounded-3 p-2 h-100"><div class="small text-muted">{{ __($label) }}</div><div class="fs-5 fw-bold text-primary-dark ltr-text">{{ $pct($m[$mk] ?? null) }}</div></div></div>
                    @endforeach
                </div>
                <div class="small text-muted mt-2">
                    {{ __('admin.models.samples', ['n' => number_format($m['n_samples'] ?? 0), 'split' => $m['split'] ?? 'test']) }}
                    @if (isset($m['auc']))· AUC {{ number_format($m['auc'], 3) }}@endif
                </div>
            </div>
            <div class="col-lg-5">
                <table class="table table-bordered text-center align-middle mb-0" aria-label="{{ __('admin.models.confusion') }}">
                    <caption class="visually-hidden">{{ __('admin.models.confusion') }}</caption>
                    <thead><tr><th scope="col"></th><th scope="col">{{ __('admin.models.pred_fake') }}</th><th scope="col">{{ __('admin.models.pred_real') }}</th></tr></thead>
                    <tbody>
                        <tr><th scope="row">{{ __('admin.models.actual_fake') }}</th><td class="verdict-real fw-bold">{{ $cm['tp'] }} <span class="small">TP</span></td><td class="verdict-fake fw-bold">{{ $cm['fn'] }} <span class="small">FN</span></td></tr>
                        <tr><th scope="row">{{ __('admin.models.actual_real') }}</th><td class="verdict-fake fw-bold">{{ $cm['fp'] }} <span class="small">FP</span></td><td class="verdict-real fw-bold">{{ $cm['tn'] }} <span class="small">TN</span></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endforeach
