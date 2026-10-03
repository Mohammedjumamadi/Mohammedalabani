<?php

namespace App\Services\Verification;

use App\Models\MediaFile;
use App\Models\VerificationRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** حذف الملفات المرفوعة وبيانات التحليل، سواء بطلب المستخدم أو تلقائيًا بانتهاء مدة الاحتفاظ. */
class VerificationCleaner
{
    /** يحذف الملف الأصلي وصور الإطارات من القرص فقط، ويُبقي سجل النتيجة (سياسة الاحتفاظ). */
    public function deleteFiles(VerificationRequest $request): void
    {
        $media = $request->mediaFile;
        $disk = Storage::disk(config('aimv.media.disk'));

        if ($media && $media->path) {
            $disk->delete($media->path);
        }

        $disk->deleteDirectory("frames/{$request->id}");

        $media?->forceFill(['file_deleted_at' => now(), 'path' => null])->save();
        $request->result?->indicators()->update(['frame_path' => null]);
    }

    /**
     * يحذف الطلب كليًا: الملفات والنتيجة والمؤشرات وروابط المشاركة. يستخدمه المستخدم لمسح بياناته.
     */
    public function destroy(VerificationRequest $request): void
    {
        $this->deleteFiles($request);

        DB::transaction(function () use ($request) {
            $request->sharedReports()->delete();
            $mediaId = $request->media_file_id;
            $request->forceDelete();
            MediaFile::withTrashed()->whereKey($mediaId)->forceDelete();
        });
    }

    /** حذف منطقي بعد إزالة الملفات: يستخدمه المدير لإزالة المحتوى المخالف مع بقاء أثر تدقيق. */
    public function takedown(VerificationRequest $request): void
    {
        $this->deleteFiles($request);
        $request->sharedReports()->update(['revoked_at' => now()]);
        $request->delete();
    }
}
