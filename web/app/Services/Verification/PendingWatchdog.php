<?php

namespace App\Services\Verification;

use App\Enums\MediaType;
use App\Enums\RequestStatus;
use App\Jobs\AnalyzeMediaJob;
use App\Models\VerificationRequest;
use App\Services\Ai\AiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * يُستدعى مع كل استطلاع لصفحة التقدم (كل ثانيتين) حتى لا يبقى الطلب صامتًا على «في قائمة الانتظار 5%»:
 * - إن كانت خدمة التحليل متوقفة يُخبر المستخدم بذلك بوضوح.
 * - إن كانت تعمل والطلب صورة، تُعيد الصفحة نفسها تشغيل التحليل دون الحاجة إلى عامل الطابور.
 * - إن كانت تعمل والطلب فيديو/صوت، فالطلب ينتظر عامل الطابور.
 */
class PendingWatchdog
{
    /** ثوانٍ في «الانتظار» قبل التدخل (التحليل الفوري للصورة عادةً يبدأ قبل ذلك). */
    public const STALE_AFTER = 8;

    /** أقل فاصل بين محاولتين تطلقهما الصفحة للطلب نفسه. */
    public const KICK_INTERVAL = 15;

    public function __construct(private readonly AiClient $ai) {}

    /** @return string|null رمز التنبيه: service_offline | retrying | waiting_worker */
    public function inspect(VerificationRequest $request): ?string
    {
        if ($request->status !== RequestStatus::Pending || ! $request->updated_at) {
            return null;
        }
        if (now()->getTimestamp() - $request->updated_at->getTimestamp() < self::STALE_AFTER) {
            return null;
        }

        if (! $this->ai->health()['online']) {
            return 'service_offline';
        }

        if ($request->mediaFile?->media_type !== MediaType::Image) {
            return 'waiting_worker';
        }

        if (Cache::add("aimv:kick:{$request->id}", true, self::KICK_INTERVAL)) {
            $id = $request->id;
            $ran = false;
            app()->terminating(function () use ($id, &$ran) {
                if ($ran) {
                    return; // يُستدعى مرة واحدة فقط حتى لو أُنهي التطبيق أكثر من مرة (Octane/الاختبارات)
                }
                $ran = true;
                try {
                    dispatch_sync(new AnalyzeMediaJob($id, immediate: true));
                } catch (\Throwable $e) {
                    Log::warning('Watchdog analysis attempt failed', ['request_id' => $id, 'error' => $e->getMessage()]);
                }
            });
        }

        return 'retrying';
    }

    /** نص التنبيه المعروض؛ المدير يرى أيضًا ما يجب فعله. */
    public function message(?string $hint): ?string
    {
        if (! $hint) {
            return null;
        }
        $text = __('verifications.progress.hints.'.$hint);
        if (auth()->user()?->isAdmin() && $hint !== 'retrying') {
            $text .= ' '.__('verifications.progress.hints.admin_fix');
        }

        return $text;
    }
}
