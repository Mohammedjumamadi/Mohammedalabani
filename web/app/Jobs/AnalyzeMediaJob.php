<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use App\Models\VerificationRequest;
use App\Notifications\VerificationFailed;
use App\Services\Ai\AiClient;
use App\Services\Ai\AiServiceException;
use App\Services\Verification\ResultRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnalyzeMediaJob implements ShouldQueue
{
    use Queueable;

    /**
     * إذا كانت خدمة التحليل متوقفة مؤقتًا (إعادة تشغيل، تحميل النماذج) لا نُفشل الطلب فورًا:
     * نعيد المحاولة 6 مرات على مدى ~18 دقيقة، فيكتمل التحليل تلقائيًا بمجرد عودة الخدمة.
     */
    public int $tries = 6;

    /** التحليل قد يطول للفيديو؛ ندع المهمة تعمل حتى 15 دقيقة. */
    public int $timeout = 900;

    public function __construct(public readonly int $verificationRequestId)
    {
        $this->onQueue('analysis');
    }

    public function backoff(): array
    {
        return [30, 60, 120, 300, 600];
    }

    public function handle(AiClient $ai, ResultRecorder $recorder): void
    {
        $request = VerificationRequest::with(['mediaFile', 'user'])->find($this->verificationRequestId);

        if (! $request || $request->status->isFinal()) {
            return;
        }

        $media = $request->mediaFile;

        if (! $media->existsOnDisk()) {
            $this->markFailed($request, 'file_missing');

            return;
        }

        $request->forceFill([
            'status' => 'uploading', 'progress' => 20, 'stage' => 'sending',
            'started_at' => $request->started_at ?? now(), 'attempts' => $request->attempts + 1,
        ])->save();

        $request->forceFill(['status' => 'analyzing', 'progress' => 45, 'stage' => 'analyzing'])->save();

        try {
            $analysis = $ai->analyze($media);
        } catch (AiServiceException $e) {
            // أخطاء عابرة (الخدمة متوقفة/مهلة): نترك الطابور يعيد المحاولة، وبعد آخر محاولة يُستدعى failed()
            if ($e->retryable && $this->attempts() < $this->tries) {
                // يعود الطلب إلى «في قائمة الانتظار» حتى لا تبقى صفحة التقدم عالقة على «جارٍ التحليل»
                $request->forceFill(['status' => 'pending', 'progress' => 5, 'stage' => 'queued'])->save();
                throw $e;
            }
            $this->markFailed($request, $e->reason, $e);

            return;
        }

        $request->forceFill(['progress' => 90, 'stage' => 'saving'])->save();
        $recorder->record($request, $analysis);
    }

    public function failed(Throwable $exception): void
    {
        $request = VerificationRequest::with(['mediaFile', 'user'])->find($this->verificationRequestId);

        if ($request && ! $request->status->isFinal()) {
            $reason = $exception instanceof AiServiceException ? $exception->reason : 'unexpected';
            $this->markFailed($request, $reason, $exception);
        }
    }

    private function markFailed(VerificationRequest $request, string $reason, ?Throwable $e = null): void
    {
        Log::error('Verification analysis failed', [
            'request' => $request->reference_number,
            'reason' => $reason,
            'exception' => $e ? get_class($e).': '.$e->getMessage() : null,
        ]);

        // نخزّن الرمز فقط؛ تُترجم الرسالة عند العرض ولا تُسرَّب تفاصيل تقنية للمستخدم
        $request->forceFill([
            'status' => 'failed',
            'progress' => 0,
            'stage' => null,
            'error_message' => $reason,
            'completed_at' => now(),
        ])->save();

        ActivityLog::record('verification.failed', 'فشل تحليل طلب تحقق', $request, ['reason' => $reason], $request->user);

        if (! $request->user->isGuestSystem()) {
            $request->user->notify(new VerificationFailed($request));
        }
    }
}
