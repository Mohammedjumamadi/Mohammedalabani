<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Classification;
use App\Enums\MediaType;
use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\VerificationRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerificationController extends Controller
{
    public function index(Request $request): View
    {
        $query = VerificationRequest::query()->with(['mediaFile', 'result', 'user'])->latest();

        if ($type = MediaType::tryFrom((string) $request->query('type'))) {
            $query->whereHas('mediaFile', fn ($q) => $q->where('media_type', $type->value));
        }
        if ($classification = Classification::tryFrom((string) $request->query('classification'))) {
            $query->whereHas('result', fn ($q) => $q->where('classification', $classification->value));
        }
        if ($status = RequestStatus::tryFrom((string) $request->query('status'))) {
            $query->where('status', $status->value);
        }
        if ($request->query('demo') === '0') {
            $query->where('is_demo', false);
        }
        if (($search = trim((string) $request->query('q'))) !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn ($q) => $q->where('reference_number', 'like', $like)
                ->orWhereHas('mediaFile', fn ($m) => $m->where('original_name', 'like', $like)->orWhere('sha256', 'like', $like))
                ->orWhereHas('user', fn ($u) => $u->where('email', 'like', $like)));
        }

        return view('admin.verifications.index', [
            'requests' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only(['q', 'type', 'classification', 'status', 'demo']),
            'failedCount' => VerificationRequest::where('status', 'failed')->count(),
        ]);
    }
}
