<?php

namespace App\Services\Verification;

use App\Enums\MediaType;
use App\Models\ActivityLog;
use App\Models\MediaFile;
use App\Models\ModelVersion;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Services\Media\FileValidator;
use App\Services\Media\MediaProbe;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * خطوات الاستقبال: التحقق من الملف، حفظه باسم عشوائي في قرص خاص، حساب SHA-256،
 * البحث عن تحليل سابق، إنشاء طلب التحقق، ثم إرسال المهمة إلى الطابور.
 */
class VerificationIngestor
{
    public function __construct(
        private readonly FileValidator $validator,
        private readonly MediaProbe $probe,
        private readonly ResultRecorder $recorder,
        private readonly AnalysisDispatcher $dispatcher,
    ) {}

    /**
     * @param  int|null  $retentionHoursOverride  عند تمريرها (رفع الزوار) تحل محل إعداد الاحتفاظ العادي بالأيام،
     *                                             لتقليل مدة تخزين ملفات لا يملكها حساب حقيقي.
     */
    public function ingest(User $user, UploadedFile $file, MediaType $type, bool $forceReanalysis = false, ?int $retentionHoursOverride = null): VerificationRequest
    {
        $checked = $this->validator->validate($file, $type);

        $disk = config('aimv.media.disk');
        $storedName = Str::random(40).'.'.$checked['extension'];
        $path = Storage::disk($disk)->putFileAs('uploads/'.now()->format('Y/m'), $file, $storedName);
        $expiresAt = $retentionHoursOverride !== null
            ? now()->addHours($retentionHoursOverride)
            : now()->addDays((int) SystemSetting::get('retention_days'));

        try {
            $absolute = Storage::disk($disk)->path($path);
            $sha256 = hash_file('sha256', $absolute);
            $info = $this->probe->probe($absolute, $type);

            [$media, $request] = DB::transaction(function () use ($user, $file, $type, $checked, $disk, $storedName, $path, $sha256, $info, $expiresAt) {
                $media = MediaFile::create([
                    'user_id' => $user->id,
                    'media_type' => $type,
                    'original_name' => $this->safeDisplayName($file->getClientOriginalName()),
                    'stored_name' => $storedName,
                    'disk' => $disk,
                    'path' => $path,
                    'mime_type' => $checked['mime'],
                    'extension' => $checked['extension'],
                    'size_bytes' => $file->getSize(),
                    'sha256' => $sha256,
                    'width' => $info['width'],
                    'height' => $info['height'],
                    'duration_seconds' => $info['duration_seconds'],
                    'metadata' => $info['metadata'] ?: null,
                    'consented_at' => now(),
                    'expires_at' => $expiresAt,
                ]);

                $request = VerificationRequest::create([
                    'user_id' => $user->id,
                    'media_file_id' => $media->id,
                    'status' => 'pending',
                    'progress' => 5,
                    'stage' => 'queued',
                ]);

                return [$media, $request];
            });
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($path);
            throw $e;
        }

        ActivityLog::record('upload.created', 'رفع ملف للتحقق', $request, [
            'type' => $type->value, 'size' => $media->size_bytes, 'sha256' => $sha256,
        ], $user);

        $previous = $forceReanalysis ? null : $this->findReusableAnalysis($sha256, $type, $request->id);

        if ($previous) {
            $this->recorder->recordReused($request, $previous);
            ActivityLog::record('verification.reused', 'إعادة استخدام نتيجة تحليل سابق', $request, ['source' => $previous->reference_number], $user);

            return $request->refresh();
        }

        $this->dispatcher->dispatch($request->id);

        return $request->refresh();
    }

    /**
     * يبحث عن تحليل مكتمل لملف بالبصمة نفسها، بشرط أن يكون بنموذج ما زال نشطًا حتى لا نعيد نتيجة نموذج قديم.
     */
    public function findReusableAnalysis(string $sha256, MediaType $type, int $excludeRequestId): ?VerificationRequest
    {
        $activeModelIds = ModelVersion::query()->where('is_active', true)->where('media_type', $type->value)->pluck('id');

        if ($activeModelIds->isEmpty()) {
            return null;
        }

        return VerificationRequest::query()
            ->where('id', '!=', $excludeRequestId)
            ->whereIn('status', ['completed', 'needs_review'])
            ->where('is_demo', false)
            ->whereHas('mediaFile', fn ($q) => $q->where('sha256', $sha256)->where('media_type', $type->value))
            ->whereHas('result', fn ($q) => $q->whereIn('model_version_id', $activeModelIds))
            ->latest('completed_at')
            ->first();
    }

    /** اسم العرض فقط: بلا مسارات أو أحرف تحكم. لا يُستخدم أبدًا في المسار الفعلي للتخزين. */
    private function safeDisplayName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';

        return Str::limit($name !== '' ? $name : 'file', 150, '');
    }
}
