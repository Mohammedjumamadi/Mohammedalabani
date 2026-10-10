<?php

namespace App\Http\Controllers;

use App\Enums\MediaType;
use App\Models\ActivityLog;
use App\Models\DetectedIndicator;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Services\Media\FileValidator;
use App\Services\Verification\PendingWatchdog;
use App\Services\Verification\VerificationIngestor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * تجربة «بلا تسجيل»: يرفع الزائر ملفًا واحدًا من الصفحة الرئيسية ويرى نتيجته فورًا،
 * لكن تنزيل تقرير PDF ومشاركته والاحتفاظ بسجل يبقى حصرًا للمستخدمين المسجَّلين.
 *
 * الملكية هنا لا تُبنى على تسجيل الدخول بل على مزيج من: (1) ملكية السجل لحساب الزائر النظامي،
 * و(2) وجود الرقم المرجعي في جلسة المتصفح نفسها التي أنشأته. فلا يستطيع زائر آخر فتح نتيجة غيره
 * حتى لو خمّن رقمها المرجعي.
 */
class GuestVerificationController extends Controller
{
    private const SESSION_KEY = 'guest_verification_refs';

    public function store(Request $request, VerificationIngestor $ingestor): RedirectResponse|JsonResponse
    {
        abort_unless(config('aimv.guest.enabled'), 404);

        $data = $request->validate([
            'media_type' => ['required', Rule::enum(MediaType::class)],
            'file' => ['required', 'file'],
            'consent' => ['accepted'],
        ], [
            'consent.accepted' => __('upload.errors.consent'),
            'file.required' => __('upload.errors.required'),
        ]);

        $verification = $ingestor->ingest(
            User::guestSystem(),
            $request->file('file'),
            MediaType::from($data['media_type']),
            forceReanalysis: false,
            retentionHoursOverride: (int) config('aimv.guest.retention_hours'),
        );

        $this->rememberInSession($request, $verification->reference_number);

        $target = $verification->status->isFinal()
            ? route('guest.show', $verification)
            : route('guest.progress', $verification);

        if ($request->expectsJson()) {
            return response()->json(['redirect' => $target]);
        }

        return redirect($target);
    }

    public function progress(Request $request, VerificationRequest $verification): View|RedirectResponse
    {
        $this->assertOwnedByThisSession($request, $verification);

        if ($verification->status->isFinal()) {
            return redirect()->route('guest.show', $verification);
        }

        return view('guest.progress', ['verification' => $verification->load('mediaFile')]);
    }

    public function status(Request $request, VerificationRequest $verification, PendingWatchdog $watchdog): JsonResponse
    {
        $this->assertOwnedByThisSession($request, $verification);

        $final = $verification->status->isFinal();
        $hint = $watchdog->inspect($verification);

        return response()->json([
            'status' => $verification->status->value,
            'status_label' => $verification->status->label(),
            'progress' => (int) $verification->progress,
            'stage' => $verification->stage,
            'stage_label' => $verification->stage ? __('verifications.stages.'.$verification->stage) : null,
            'final' => $final,
            'failed' => $verification->status->value === 'failed',
            'error' => $verification->status->value === 'failed' ? __('ai_errors.'.($verification->error_message ?: 'unexpected')) : null,
            'url' => $final ? route('guest.show', $verification) : null,
            'hint' => $hint,
            'hint_text' => $watchdog->message($hint),
        ])->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, VerificationRequest $verification): View
    {
        $this->assertOwnedByThisSession($request, $verification);

        $verification->load(['mediaFile', 'result.indicators', 'result.modelVersion']);

        return view('guest.result', [
            'verification' => $verification,
            'result' => $verification->result,
            'media' => $verification->mediaFile,
        ]);
    }

    public function media(Request $request, VerificationRequest $verification): BinaryFileResponse
    {
        $this->assertOwnedByThisSession($request, $verification);

        $media = $verification->mediaFile;
        abort_unless($media->existsOnDisk(), 404);

        $response = response()->file(Storage::disk($media->disk)->path($media->path), [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="media.'.$media->extension.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    public function frame(Request $request, VerificationRequest $verification, DetectedIndicator $indicator): BinaryFileResponse
    {
        $this->assertOwnedByThisSession($request, $verification);

        abort_unless($indicator->verification_result_id === $verification->result?->id && $indicator->frame_path, 404);

        $disk = Storage::disk(config('aimv.media.disk'));
        abort_unless($disk->exists($indicator->frame_path), 404);

        $response = response()->file($disk->path($indicator->frame_path), [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    /**
     * زر «طباعة التقرير» للزائر (يعمل حتى دون JavaScript): يحفظ مسار استلام النتيجة كوجهة مقصودة،
     * فيعيده الدخول أو التسجيل إليها مباشرة بدل لوحة التحكم.
     */
    public function reportAccess(Request $request, VerificationRequest $verification, string $via): RedirectResponse
    {
        $this->assertOwnedByThisSession($request, $verification);
        abort_unless($verification->status->hasResult(), 404);

        $request->session()->put('url.intended', route('guest.claim', $verification));

        return redirect()->route($via);
    }

    /**
     * بعد الدخول أو التسجيل: تُنقل نتيجة الزائر إلى حساب المستخدم ثم يُفتح تقرير الطباعة.
     * لا يُنقل إلا سجل يخص حساب الزوار النظامي ومحفوظ رقمه في جلسة هذا المتصفح نفسها،
     * فلا يستطيع أحد الاستيلاء على نتيجة غيره بمعرفة رقمها المرجعي.
     */
    public function claim(Request $request, VerificationRequest $verification): RedirectResponse
    {
        $user = $request->user();

        // إعادة فتح الرابط بعد الاستلام (تحديث الصفحة أو زر الرجوع)
        if ($verification->user_id === $user->id) {
            return redirect()->route('verifications.print', $verification);
        }

        $refs = $request->session()->get(self::SESSION_KEY, []);
        $claimable = $verification->user_id === User::guestSystem()->id
            && in_array($verification->reference_number, $refs, true)
            && $verification->status->hasResult();

        if (! $claimable) {
            return redirect()->route('dashboard')->with('error', __('reports.print.claim_unavailable'));
        }

        DB::transaction(function () use ($verification, $user) {
            $verification->forceFill(['user_id' => $user->id])->save();

            // الملف صار يملكه حساب حقيقي: تسري عليه مدة الاحتفاظ العادية بدل مدة الزوار القصيرة
            $verification->mediaFile?->forceFill([
                'user_id' => $user->id,
                'expires_at' => now()->addDays((int) SystemSetting::get('retention_days')),
            ])->save();
        });

        $request->session()->put(self::SESSION_KEY, array_values(array_diff($refs, [$verification->reference_number])));
        ActivityLog::record('verification.claimed', 'حفظ نتيجة زائر في حساب المستخدم', $verification, user: $user);

        return redirect()->route('verifications.print', [$verification, 'autoprint' => 1])->with('success', __('reports.print.claimed'));
    }

    /**
     * بيانات نموذج الرفع في الصفحة الرئيسية (الامتدادات المسموحة والحد الأقصى).
     * لا تستعلم حالة خدمة الذكاء الاصطناعي هنا عمدًا (تحمّل شبكي على كل زيارة للرئيسية)؛
     * فشل الخدمة يظهر برسالة واضحة أثناء التحليل نفسه إذا حدث، كما في تدفق المستخدم المسجَّل.
     */
    public static function uploaderContext(FileValidator $validator): array
    {
        $types = [];
        foreach (MediaType::cases() as $type) {
            $types[$type->value] = $validator->allowedExtensions($type);
        }

        return [
            'guestTypes' => $types,
            'guestMaxBytes' => $validator->maxBytes(),
            'guestMaxLabel' => $validator->formatBytes($validator->maxBytes()),
        ];
    }

    private function rememberInSession(Request $request, string $reference): void
    {
        $refs = $request->session()->get(self::SESSION_KEY, []);
        $refs[] = $reference;
        // لا حاجة لتذكّر أكثر من عدد قليل؛ حد الرفع بالأصل 5 في الساعة لكل عنوان IP
        $request->session()->put(self::SESSION_KEY, array_slice(array_unique($refs), -20));
    }

    /** 404 موحّدة سواء كان السجل غير موجود، أو يخص حسابًا حقيقيًا، أو لا يخص جلسة هذا المتصفح. */
    private function assertOwnedByThisSession(Request $request, VerificationRequest $verification): void
    {
        $refs = $request->session()->get(self::SESSION_KEY, []);

        abort_unless(
            $verification->user_id === User::guestSystem()->id && in_array($verification->reference_number, $refs, true),
            404,
        );
    }
}
