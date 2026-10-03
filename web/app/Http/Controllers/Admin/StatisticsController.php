<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Classification;
use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Models\ModelVersion;
use App\Models\VerificationRequest;
use App\Models\VerificationResult;
use App\Support\DateBuckets;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StatisticsController extends Controller
{
    public function index(): View
    {
        $requests = fn () => VerificationRequest::query();
        $total = max(1, $requests()->count());

        $classification = collect(Classification::cases())->mapWithKeys(fn (Classification $c) => [
            $c->value => VerificationResult::query()->where('classification', $c->value)->count(),
        ]);

        $perType = collect(MediaType::cases())->map(function (MediaType $t) {
            $base = fn () => VerificationRequest::query()->whereHas('mediaFile', fn ($q) => $q->where('media_type', $t->value));

            return [
                'label' => $t->label(),
                'icon' => $t->icon(),
                'total' => $base()->count(),
                'failed' => $base()->where('status', 'failed')->count(),
                'avg_time' => VerificationResult::query()->whereHas('verificationRequest.mediaFile', fn ($q) => $q->where('media_type', $t->value))->avg('processing_time'),
            ];
        });

        return view('admin.statistics', [
            'daily' => DateBuckets::daily($requests(), days: 30),
            'monthly' => DateBuckets::monthly($requests(), months: 12),
            'classification' => $classification,
            'perType' => $perType,
            'failedRate' => round($requests()->where('status', 'failed')->count() / $total * 100, 1),
            'models' => ModelVersion::query()->orderBy('media_type')->get(),
            'hasDemo' => $requests()->where('is_demo', true)->exists(),
        ]);
    }

    /** تصدير عمليات التحقق كملف CSV (بلا بيانات شخصية: معرّف المستخدم فقط). */
    public function export(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM ليفتح Excel العربية بشكل صحيح
            fputcsv($out, ['reference', 'created_at', 'user_id', 'media_type', 'status', 'classification', 'manipulation_probability', 'confidence', 'model', 'model_version', 'processing_time', 'demo']);

            VerificationRequest::query()->with(['mediaFile', 'result'])->orderBy('id')->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $r) {
                    fputcsv($out, [
                        $r->reference_number, $r->created_at->toDateTimeString(), $r->user_id, $r->mediaFile->media_type->value, $r->status->value,
                        $r->result?->classification->value, $r->result?->manipulation_probability, $r->result?->confidence_score,
                        $r->result?->model_name, $r->result?->model_version, $r->result?->processing_time, $r->is_demo ? 'yes' : 'no',
                    ]);
                }
            });
            fclose($out);
        }, 'verifications-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
