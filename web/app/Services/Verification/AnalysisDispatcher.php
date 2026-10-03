<?php

namespace App\Services\Verification;

use App\Jobs\AnalyzeMediaJob;
use Illuminate\Support\Facades\Log;

/** يرسل مهمة التحليل إلى الطابور دون أن يُسقط الطلب الحالي إذا أعاد مشغّل الطابور المتزامن استثناءً. */
class AnalysisDispatcher
{
    public function dispatch(int $verificationRequestId): void
    {
        try {
            AnalyzeMediaJob::dispatch($verificationRequestId)->afterCommit();
        } catch (\Throwable $e) {
            // مع مشغّل sync يصل استثناء المهمة إلى هنا؛ الطلب سُجّل فاشلًا أصلًا داخل المهمة نفسها
            Log::warning('Analysis job threw during dispatch', ['request_id' => $verificationRequestId, 'error' => $e->getMessage()]);
        }
    }
}
