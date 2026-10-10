<?php

namespace App\Services\Verification;

use App\Enums\MediaType;
use App\Jobs\AnalyzeMediaJob;
use App\Models\VerificationRequest;
use Illuminate\Support\Facades\Log;

/**
 * يرسل مهمة التحليل دون أن يُسقط الطلب الحالي إذا أعاد مشغّل الطابور المتزامن استثناءً.
 *
 * الصور تُحلَّل خلال ثوانٍ، فتُنفَّذ فورًا بعد إرسال الاستجابة للمتصفح دون انتظار عامل الطابور،
 * فلا تعلق على «في قائمة الانتظار» إن كان العامل متوقفًا. إذا تعذّر التحليل الفوري لسبب عابر
 * تنتقل المهمة إلى الطابور (انظر AnalyzeMediaJob). الفيديو والصوت يبقيان في الطابور لأنهما أطول.
 */
class AnalysisDispatcher
{
    public function dispatch(int $verificationRequestId): void
    {
        $type = VerificationRequest::with('mediaFile')->find($verificationRequestId)?->mediaFile?->media_type;

        if ($type === MediaType::Image && config('aimv.ai.immediate_images', true)) {
            $ran = false;
            app()->terminating(function () use ($verificationRequestId, &$ran) {
                if (! $ran) { // مرة واحدة فقط حتى لو أُنهي التطبيق أكثر من مرة (Octane/الاختبارات)
                    $ran = true;
                    $this->run(new AnalyzeMediaJob($verificationRequestId, immediate: true));
                }
            });

            return;
        }

        $this->run(fn () => AnalyzeMediaJob::dispatch($verificationRequestId)->afterCommit());
    }

    private function run(AnalyzeMediaJob|\Closure $job): void
    {
        try {
            $job instanceof AnalyzeMediaJob ? dispatch_sync($job) : $job();
        } catch (\Throwable $e) {
            // مع التنفيذ المتزامن يصل استثناء المهمة إلى هنا؛ الطلب سُجّل فاشلًا أصلًا داخل المهمة نفسها
            Log::warning('Analysis job threw during dispatch', ['error' => $e->getMessage()]);
        }
    }
}
