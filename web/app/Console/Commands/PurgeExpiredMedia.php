<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\MediaFile;
use App\Services\Verification\VerificationCleaner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeExpiredMedia extends Command
{
    protected $signature = 'media:purge-expired';

    protected $description = 'يحذف الملفات المرفوعة التي انتهت مدة الاحتفاظ بها ويُبقي بيانات النتائج';

    public function handle(VerificationCleaner $cleaner): int
    {
        $count = 0;

        MediaFile::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->whereNull('file_deleted_at')
            ->with('verificationRequest.result')
            ->chunkById(100, function ($files) use ($cleaner, &$count) {
                foreach ($files as $media) {
                    if ($media->verificationRequest) {
                        $cleaner->deleteFiles($media->verificationRequest);
                    } else {
                        if ($media->path) {
                            Storage::disk($media->disk)->delete($media->path);
                        }
                        $media->forceFill(['file_deleted_at' => now(), 'path' => null])->save();
                    }
                    $count++;
                }
            });

        if ($count > 0) {
            ActivityLog::record('retention.purged', 'حذف تلقائي للملفات المنتهية', properties: ['count' => $count]);
        }

        $this->info("Purged {$count} expired file(s).");

        return self::SUCCESS;
    }
}
