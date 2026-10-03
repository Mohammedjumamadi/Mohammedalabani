<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\VerificationRequest;
use App\Services\Report\ReportBuilder;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function pdf(VerificationRequest $verification, ReportBuilder $builder): Response
    {
        $this->authorize('view', $verification);
        abort_unless($verification->status->hasResult(), 404);

        ActivityLog::record('report.downloaded', 'تنزيل تقرير PDF', $verification);

        return response($builder->pdf($verification), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="report-'.$verification->reference_number.'.pdf"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * تقرير HTML منسّق للطباعة من المتصفح. الحماية على الخادم: المسار خلف auth،
     * والسياسة تسمح لصاحب الطلب (أو المدير) فقط، فلا يطبع أحد تقرير غيره بتخمين الرقم المرجعي.
     */
    public function print(VerificationRequest $verification, ReportBuilder $builder): View
    {
        $this->authorize('view', $verification);
        $verification->loadMissing(['mediaFile', 'result.indicators']);
        abort_unless($verification->status->hasResult() && $verification->result, 404);

        $verifyUrl = route('reports.verify', $verification->reference_number);

        ActivityLog::record('report.printed', 'فتح تقرير الطباعة', $verification);

        return view('reports.print', [
            'verification' => $verification,
            'result' => $verification->result,
            'media' => $verification->mediaFile,
            'indicators' => $verification->result->indicators
                ->sortBy(fn ($i) => [['high' => 0, 'medium' => 1, 'low' => 2, 'info' => 3][$i->severity->value] ?? 4, -($i->score ?? 0)])
                ->values(),
            'verifyUrl' => $verifyUrl,
            'qr' => $builder->qrDataUri($verifyUrl),
        ]);
    }
}
