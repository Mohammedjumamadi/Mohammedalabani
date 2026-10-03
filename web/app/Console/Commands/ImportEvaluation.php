<?php

namespace App\Console\Commands;

use App\Enums\MediaType;
use App\Models\ModelVersion;
use App\Services\Evaluation\MetricsImporter;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ImportEvaluation extends Command
{
    protected $signature = 'evaluation:import {file : تقرير JSON الناتج عن evaluation/evaluate.py}';

    protected $description = 'يستورد مقاييس التقييم الحقيقية إلى نسخة النموذج المطابقة (مع التحقق من اتساقها الحسابي)';

    public function handle(MetricsImporter $importer): int
    {
        $path = $this->argument('file');
        $report = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        if (! is_array($report) || empty($report['model']['name']) || empty($report['model']['version']) || MediaType::tryFrom((string) ($report['media_type'] ?? '')) === null) {
            $this->error('ملف غير صالح: يجب أن يحتوي على model.name وmodel.version وmedia_type.');

            return self::FAILURE;
        }

        $model = ModelVersion::firstOrCreate(
            ['name' => $report['model']['name'], 'version' => $report['model']['version'], 'media_type' => $report['media_type']],
            ['source_url' => $report['model']['source'] ?? null, 'license' => $report['model']['license'] ?? null, 'is_active' => true],
        );

        try {
            $metrics = $importer->import($model, $report);
        } catch (ValidationException $e) {
            $this->error($e->validator->errors()->first());

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%s v%s: accuracy=%.1f%% precision=%.1f%% recall=%.1f%% f1=%.1f%% (n=%d)',
            $model->name, $model->version, $metrics['accuracy'] * 100, $metrics['precision'] * 100, $metrics['recall'] * 100, $metrics['f1'] * 100, $metrics['n_samples'],
        ));

        return self::SUCCESS;
    }
}
