@if (session('success'))
    <div class="alert alert-success d-flex align-items-start gap-2" role="status">
        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
        <div>{{ session('success') }}</div>
    </div>
@endif

@if (session('status'))
    <div class="alert alert-info d-flex align-items-start gap-2" role="status">
        <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
        <div>{{ session('status') }}</div>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
        <i class="bi bi-x-octagon-fill" aria-hidden="true"></i>
        <div>{{ session('error') }}</div>
    </div>
@endif

@if ($errors->any() && ! isset($hideErrorSummary))
    <div class="alert alert-danger" role="alert">
        <div class="d-flex align-items-center gap-2 fw-bold mb-1">
            <i class="bi bi-x-octagon-fill" aria-hidden="true"></i> {{ __('common.fix_errors') }}
        </div>
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
