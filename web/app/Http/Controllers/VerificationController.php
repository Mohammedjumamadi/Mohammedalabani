<?php

namespace App\Http\Controllers;

use App\Enums\Classification;
use App\Enums\MediaType;
use App\Enums\RequestStatus;
use App\Http\Requests\StoreVerificationRequest;
use App\Models\ActivityLog;
use App\Models\DetectedIndicator;
use App\Models\VerificationRequest;
use App\Services\Ai\AiClient;
use App\Services\Media\FileValidator;
use App\Services\Verification\AnalysisDispatcher;
use App\Services\Verification\VerificationCleaner;
use App\Services\Verification\VerificationIngestor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VerificationController extends Controller
{
    public function create(FileValidator $validator, AiClient $ai): View
    {
        $types = [];
        foreach (MediaType::cases() as $type) {
            $types[$type->value] = $validator->allowedExtensions($type);
        }

        return view('verifications.create', [
            'types' => $types,
            'maxBytes' => $validator->maxBytes(),
            'maxLabel' => $validator->formatBytes($validator->maxBytes()),
            'serverLimited' => $validator->maxBytes() < $validator->configuredMaxBytes(),
            'aiOnline' => $ai->health()['online'],
        ]);
    }

    public function store(StoreVerificationRequest $request, VerificationIngestor $ingestor): RedirectResponse|JsonResponse
    {
        $verification = $ingestor->ingest(
            $request->user(),
            $request->file('file'),
            MediaType::from($request->validated('media_type')),
            $request->boolean('reanalyze'),
        );

        $final = $verification->status->isFinal();
        $target = route($final ? 'verifications.show' : 'verifications.progress', $verification);

        // نموذج الرفع يرسل XHR ليُظهر شريط التقدم، ويحتاج عنوان الوجهة بصيغة JSON
        if ($request->expectsJson()) {
            if ($final) {
                session()->flash('success', __('verifications.reused_notice'));
            }

            return response()->json(['redirect' => $target]);
        }

        return $final
            ? redirect($target)->with('success', __('verifications.reused_notice'))
            : redirect($target);
    }

    public function index(Request $request): View
    {
        $query = VerificationRequest::query()
            ->ownedBy($request->user())
            ->with(['mediaFile', 'result'])
            ->latest();

        if ($type = MediaType::tryFrom((string) $request->query('type'))) {
            $query->whereHas('mediaFile', fn ($q) => $q->where('media_type', $type->value));
        }
        if ($classification = Classification::tryFrom((string) $request->query('classification'))) {
            $query->whereHas('result', fn ($q) => $q->where('classification', $classification->value));
        }
        if ($status = RequestStatus::tryFrom((string) $request->query('status'))) {
            $query->where('status', $status->value);
        }
        if (($search = trim((string) $request->query('q'))) !== '') {
            $query->where(function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where('reference_number', 'like', $like)
                    ->orWhereHas('mediaFile', fn ($m) => $m->where('original_name', 'like', $like));
            });
        }

        return view('verifications.index', [
            'requests' => $query->paginate(10)->withQueryString(),
            'filters' => $request->only(['type', 'classification', 'status', 'q']),
        ]);
    }

    public function progress(VerificationRequest $verification): View|RedirectResponse
    {
        $this->authorize('view', $verification);

        if ($verification->status->isFinal()) {
            return redirect()->route('verifications.show', $verification);
        }

        return view('verifications.progress', ['verification' => $verification->load('mediaFile')]);
    }

    /** استجابة JSON خفيفة يستطلعها شريط التقدم كل ثانيتين. */
    public function status(VerificationRequest $verification): JsonResponse
    {
        $this->authorize('view', $verification);

        $final = $verification->status->isFinal();

        return response()->json([
            'status' => $verification->status->value,
            'status_label' => $verification->status->label(),
            'progress' => (int) $verification->progress,
            'stage' => $verification->stage,
            'stage_label' => $verification->stage ? __('verifications.stages.'.$verification->stage) : null,
            'final' => $final,
            'failed' => $verification->status === RequestStatus::Failed,
            'error' => $verification->status === RequestStatus::Failed ? __('ai_errors.'.($verification->error_message ?: 'unexpected')) : null,
            'url' => $final ? route('verifications.show', $verification) : null,
        ])->header('Cache-Control', 'no-store');
    }

    public function show(VerificationRequest $verification): View
    {
        $this->authorize('view', $verification);

        $verification->load(['mediaFile', 'result.indicators', 'result.modelVersion', 'reusedFrom', 'user']);

        return view('verifications.show', [
            'verification' => $verification,
            'result' => $verification->result,
            'media' => $verification->mediaFile,
            'shared' => $verification->sharedReports()->whereNull('revoked_at')->latest()->first(),
        ]);
    }

    public function retry(VerificationRequest $verification, AnalysisDispatcher $dispatcher): RedirectResponse
    {
        $this->authorize('retry', $verification);

        if (! $verification->mediaFile->existsOnDisk()) {
            return back()->with('error', __('ai_errors.file_missing'));
        }

        $verification->forceFill([
            'status' => 'pending', 'progress' => 5, 'stage' => 'queued', 'error_message' => null, 'completed_at' => null,
        ])->save();

        $dispatcher->dispatch($verification->id);
        ActivityLog::record('verification.retried', 'إعادة محاولة التحليل', $verification);

        return redirect()->route('verifications.progress', $verification);
    }

    public function destroy(VerificationRequest $verification, VerificationCleaner $cleaner): RedirectResponse
    {
        $this->authorize('delete', $verification);

        $isOwner = $verification->user_id === auth()->id();
        ActivityLog::record($isOwner ? 'verification.deleted' : 'verification.taken_down', 'حذف عملية تحقق', $verification, [
            'reference' => $verification->reference_number,
        ]);

        $isOwner ? $cleaner->destroy($verification) : $cleaner->takedown($verification);

        return redirect()
            ->route($isOwner ? 'verifications.index' : 'admin.verifications.index')
            ->with('success', __('verifications.deleted'));
    }

    /** يقدّم الملف الأصلي عبر مسار محمي، ولا يُكشف مساره الداخلي أبدًا. */
    public function media(VerificationRequest $verification): BinaryFileResponse
    {
        $this->authorize('viewMedia', $verification);

        $media = $verification->mediaFile;
        abort_unless($media->existsOnDisk(), 404);

        return $this->privateFile(Storage::disk($media->disk)->path($media->path), $media->mime_type, [
            'Content-Disposition' => 'inline; filename="media.'.$media->extension.'"',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public function frame(VerificationRequest $verification, DetectedIndicator $indicator): BinaryFileResponse
    {
        $this->authorize('viewMedia', $verification);

        // المؤشر يجب أن يخص نتيجة هذا الطلب تحديدًا (منع الوصول لإطارات طلبات الآخرين)
        abort_unless($indicator->verification_result_id === $verification->result?->id && $indicator->frame_path, 404);

        $disk = Storage::disk(config('aimv.media.disk'));
        abort_unless($disk->exists($indicator->frame_path), 404);

        return $this->privateFile($disk->path($indicator->frame_path), 'image/jpeg');
    }

    /** ملف خاص: لا يُخزَّن في أي وسيط مؤقت عام، ولا يُخمَّن نوعه من المتصفح. */
    private function privateFile(string $path, string $mime, array $headers = []): BinaryFileResponse
    {
        $response = response()->file($path, $headers + [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
