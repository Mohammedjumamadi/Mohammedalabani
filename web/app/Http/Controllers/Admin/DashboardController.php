<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Classification;
use App\Enums\MediaType;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\MediaFile;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Models\VerificationResult;
use App\Services\Ai\AiClient;
use App\Support\DateBuckets;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(AiClient $ai): View
    {
        $requests = fn () => VerificationRequest::query();
        $classCount = fn (Classification $c) => VerificationResult::query()->where('classification', $c->value)->count();

        $byType = MediaFile::query()->whereHas('verificationRequest')
            ->selectRaw('media_type, count(*) as total')->groupBy('media_type')->pluck('total', 'media_type');

        $avgProcessing = VerificationResult::query()
            ->whereHas('verificationRequest', fn ($q) => $q->where('is_demo', false))->avg('processing_time');

        $models = VerificationResult::query()
            ->whereNotNull('model_version_id')
            ->selectRaw('model_name, model_version, count(*) as total')
            ->groupBy('model_name', 'model_version')->orderByDesc('total')->limit(5)->get();

        // الحساب النظامي لتحليلات الزوار غير المسجَّلين لا يُحسب ضمن إحصاءات المستخدمين
        $realUsers = fn () => User::query()->where('email', '!=', User::GUEST_EMAIL);

        return view('admin.dashboard', [
            'users' => [
                'total' => $realUsers()->count(),
                'active' => $realUsers()->where('status', UserStatus::Active->value)->count(),
                'suspended' => $realUsers()->where('status', UserStatus::Suspended->value)->count(),
            ],
            'verifications' => [
                'total' => $requests()->count(),
                'completed' => $requests()->whereIn('status', ['completed', 'needs_review'])->count(),
                'failed' => $requests()->where('status', 'failed')->count(),
                'real' => $classCount(Classification::Real),
                'fake' => $classCount(Classification::Fake),
                'uncertain' => $classCount(Classification::Uncertain),
            ],
            'typeChart' => [
                'labels' => collect(MediaType::cases())->map->label()->all(),
                'counts' => collect(MediaType::cases())->map(fn (MediaType $t) => (int) ($byType[$t->value] ?? 0))->all(),
            ],
            'daily' => DateBuckets::daily($requests()),
            'monthly' => DateBuckets::monthly($requests()),
            'ai' => $ai->health(true),
            'storage' => (int) MediaFile::query()->whereNull('file_deleted_at')->sum('size_bytes'),
            'avgProcessing' => $avgProcessing,
            'models' => $models,
            'activities' => ActivityLog::query()->with('user')->latest('created_at')->limit(8)->get(),
            'hasDemo' => $requests()->where('is_demo', true)->exists(),
        ]);
    }
}
