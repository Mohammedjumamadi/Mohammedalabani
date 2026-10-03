<?php

namespace App\Services\Verification;

use App\Models\ActivityLog;
use App\Models\ModelVersion;
use App\Models\VerificationRequest;
use App\Models\VerificationResult;
use App\Notifications\VerificationCompleted;
use App\Services\Ai\AiAnalysis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ResultRecorder
{
    public function __construct(private readonly ClassificationSettings $settings) {}

    /** يحفظ نتيجة تحليل حقيقية وكل مؤشراتها ويُنهي الطلب. */
    public function record(VerificationRequest $request, AiAnalysis $analysis): VerificationResult
    {
        $classification = $this->settings->classify($analysis->manipulationProbability);

        $result = DB::transaction(function () use ($request, $analysis, $classification) {
            $model = ModelVersion::firstOrCreate(
                ['name' => $analysis->modelName, 'version' => $analysis->modelVersion, 'media_type' => $analysis->mediaType->value],
                ['source_url' => $analysis->modelSource, 'license' => $analysis->modelLicense, 'is_active' => true],
            );

            $result = VerificationResult::create([
                'verification_request_id' => $request->id,
                'classification' => $classification,
                'confidence_score' => $analysis->confidence,
                'authenticity_probability' => $analysis->authenticityProbability(),
                'manipulation_probability' => $analysis->manipulationProbability,
                'risk_level' => $classification->riskLevel(),
                'model_version_id' => $model->id,
                'model_name' => $analysis->modelName,
                'model_version' => $analysis->modelVersion,
                'processing_time' => $analysis->processingTime,
                'thresholds' => $this->settings->thresholds(),
                'raw_response' => $analysis->raw,
                'completed_at' => now(),
            ]);

            $frames = $this->storeFrames($request, $analysis->frames);

            foreach ($analysis->indicators as $indicator) {
                $frameIndex = $indicator['frame_index'] ?? null;
                unset($indicator['frame_index']);

                $result->indicators()->create($indicator + [
                    'frame_path' => $frameIndex !== null ? ($frames[$frameIndex] ?? null) : null,
                ]);
            }

            $result->forceFill(['explanation' => (new ExplanationBuilder)->build($result->load('indicators'), $analysis->mediaType, 'ar')])->save();

            $request->forceFill([
                'status' => $classification->requestStatus(),
                'progress' => 100,
                'stage' => 'done',
                'error_message' => null,
                'completed_at' => now(),
            ])->save();

            return $result;
        });

        ActivityLog::record('verification.completed', 'اكتمل تحليل طلب تحقق', $request, [
            'classification' => $classification->value,
            'model' => $analysis->modelName,
        ], $request->user);

        // لا نُشعر الحساب النظامي لتحليلات الزوار؛ لا أحد يقرأ إشعاراته
        if (! $request->user->isGuestSystem()) {
            $request->user->notify(new VerificationCompleted($request->fresh('result')));
        }

        return $result;
    }

    /**
     * يعيد استخدام نتيجة تحليل سابق لملف بالبصمة نفسها، بدل تحليله من جديد.
     * تُعاد المطابقة مع الحدود الحالية، وتُنسخ الإطارات المشبوهة حتى لا يتأثر الطلب بحذف بيانات الآخر.
     */
    public function recordReused(VerificationRequest $request, VerificationRequest $source): VerificationResult
    {
        $original = $source->result()->with('indicators')->firstOrFail();
        $classification = $this->settings->classify($original->manipulation_probability);
        $disk = Storage::disk(config('aimv.media.disk'));

        return DB::transaction(function () use ($request, $source, $original, $classification, $disk) {
            $result = VerificationResult::create([
                'verification_request_id' => $request->id,
                'classification' => $classification,
                'confidence_score' => $original->confidence_score,
                'authenticity_probability' => $original->authenticity_probability,
                'manipulation_probability' => $original->manipulation_probability,
                'risk_level' => $classification->riskLevel(),
                'explanation' => $original->explanation,
                'model_version_id' => $original->model_version_id,
                'model_name' => $original->model_name,
                'model_version' => $original->model_version,
                'processing_time' => $original->processing_time,
                'thresholds' => $this->settings->thresholds(),
                'raw_response' => $original->raw_response,
                'completed_at' => now(),
            ]);

            foreach ($original->indicators as $indicator) {
                $framePath = null;
                if ($indicator->frame_path && $disk->exists($indicator->frame_path)) {
                    $framePath = "frames/{$request->id}/".bin2hex(random_bytes(12)).'.jpg';
                    $disk->copy($indicator->frame_path, $framePath);
                }

                $result->indicators()->create($indicator->only([
                    'code', 'category', 'severity', 'score', 'description', 'timestamp_start', 'timestamp_end', 'details',
                ]) + ['frame_path' => $framePath]);
            }

            $request->forceFill([
                'status' => $classification->requestStatus(),
                'progress' => 100,
                'stage' => 'done',
                'reused_from_id' => $source->id,
                'started_at' => now(),
                'completed_at' => now(),
            ])->save();

            return $result;
        });
    }

    /**
     * يحفظ صور الإطارات المشبوهة في القرص الخاص، بعد التأكد أنها JPEG صالحة وبحجم معقول.
     *
     * @param  list<array<string, mixed>>  $frames
     * @return array<int, string> فهرس الإطار => المسار
     */
    private function storeFrames(VerificationRequest $request, array $frames): array
    {
        $paths = [];
        $disk = Storage::disk(config('aimv.media.disk'));

        foreach (array_slice($frames, 0, config('aimv.media.max_frames_stored')) as $frame) {
            $binary = base64_decode($frame['image_b64'], true);

            if ($binary === false || strlen($binary) > config('aimv.media.max_frame_bytes') || ! str_starts_with($binary, "\xFF\xD8\xFF")) {
                continue;
            }

            $path = "frames/{$request->id}/".bin2hex(random_bytes(12)).'.jpg';
            $disk->put($path, $binary);
            $paths[$frame['index']] = $path;
        }

        return $paths;
    }
}
