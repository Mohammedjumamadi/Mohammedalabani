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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnalyzeMediaJob implements ShouldQueue
{
    use Queueable;

    /**
     * إذا كانت خدمة التحليل متوقفة مؤقتًا (إعادة تشغيل، تحميل النماذج) لا نُفشل الطلب فورًا:
     * نعيد المحاولة 6 مرات على مدى ~4 دقائق، فيكتمل التحليل تلقائيًا بمجرد عودة الخدمة.
     */
    public int $tries = 6;

    /** التحليل قد يطول للفيديو؛ ندع المهمة تعمل حتى 15 دقيقة. */
    public int $timeout = 900;

    /** مهلة التحليل الفوري للصور (ثوانٍ): بعدها ينتقل الطلب إلى الطابور بدل أن يُبقي الصفحة معلّقة. */
    public const IMMEDIATE_TIMEOUT = 60;

    /**
     * @param  bool  $immediate  نُفّذ مباشرة بعد الاستجابة (الصور) لا عبر عامل الطابور
     */
    public function __construct(public readonly int $verificationRequestId, public readonly bool $immediate = false)
    {
        $this->onQueue('analysis');
    }

    public function backoff(): array
    {
        return [5, 15, 30, 60, 120];
    }

    public function handle(AiClient $ai, ResultRecorder $recorder): void
    {
        // قفل حتى لا يحلّل عامل الطابور وصفحة التقدم الطلب نفسه في الوقت نفسه
        $lock = Cache::lock("aimv:analyze:{$this->verificationRequestId}", self::IMMEDIATE_TIMEOUT + 60);
        if (! $lock->get()) {
            if (! $this->immediate && $this->job) {
                $this->release(10);
            }

            return;
        }

        try {
            $requeue = $this->analyze($ai, $recorder);
        } finally {
            $lock->release();
        }

        if ($requeue) {
            // تعذّر التحليل الفوري (الخدمة متوقفة أو تحمّل النماذج): نسلّمه للطابور ليعيد المحاولة
            try {
                self::dispatch($this->verificationRequestId)->delay(now()->addSeconds(5));
            } catch (Throwable) {
                // مع مشغّل sync تُنفَّذ المهمة فورًا وتسجّل فشلها بنفسها
            }
        }
    }

    /** @return bool هل يجب تسليم الطلب إلى الطابور لإعادة المحاولة */
    private function analyze(AiClient $ai, ResultRecorder $recorder): bool
    {
        $request = VerificationRequest::with(['mediaFile', 'user'])->find($this->verificationRequestId);

        if (! $request || $request->status->isFinal()) {
            return false;
        }

        $media = $request->mediaFile;

        if (! $media->existsOnDisk()) {
            $this->markFailed($request, 'file_missing');

            return false;
        }

        $request->forceFill([
            'status' => 'uploading', 'progress' => 20, 'stage' => 'sending',
            'started_at' => $request->started_at ?? now(), 'attempts' => $request->attempts + 1,
        ])->save();

        $request->forceFill(['status' => 'analyzing', 'progress' => 45, 'stage' => 'analyzing'])->save();

        if ($this->immediate) {
            @set_time_limit(self::IMMEDIATE_TIMEOUT + 30);
        }

        try {
            $analysis = $ai->analyze($media, $this->immediate ? self::IMMEDIATE_TIMEOUT : null);
        } catch (AiServiceException $e) {
            if ($e->retryable && $this->immediate) {
                $request->forceFill(['status' => 'pending', 'progress' => 5, 'stage' => 'queued'])->save();

                return true;
            }
            // أخطاء عابرة (الخدمة متوقفة/مهلة): نترك الطابور يعيد المحاولة، وبعد آخر محاولة يُستدعى failed()
            if ($e->retryable && $this->attempts() < $this->tries) {
                // يعود الطلب إلى «في قائمة الانتظار» حتى لا تبقى صفحة التقدم عالقة على «جارٍ التحليل»
                $request->forceFill(['status' => 'pending', 'progress' => 5, 'stage' => 'queued'])->save();
                throw $e;
            }
            $this->markFailed($request, $e->reason, $e);

            return false;
        }

        $request->forceFill(['progress' => 90, 'stage' => 'saving'])->save();
        $recorder->record($request, $analysis);

        return false;
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
