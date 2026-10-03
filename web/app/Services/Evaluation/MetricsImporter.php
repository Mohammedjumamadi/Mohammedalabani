<?php

namespace App\Services\Evaluation;

use App\Models\ModelVersion;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * يستورد نتائج سكربت التقييم (evaluation/evaluate.py) ويتحقق من اتساقها الحسابي،
 * فلا تُقبل مقاييس لا تنتج عن مصفوفة الارتباك نفسها (منعًا لإدخال أرقام مختلقة).
 */
class MetricsImporter
{
    private const TOLERANCE = 0.005;

    /** @return array<string, mixed> المقاييس المعاد حسابها من مصفوفة الارتباك */
    public function validate(array $data): array
    {
        $cm = $data['confusion_matrix'] ?? null;

        foreach (['tp', 'fp', 'tn', 'fn'] as $key) {
            if (! is_array($cm) || ! isset($cm[$key]) || ! is_int($cm[$key]) || $cm[$key] < 0) {
                $this->fail('confusion_matrix');
            }
        }

        $n = $cm['tp'] + $cm['fp'] + $cm['tn'] + $cm['fn'];
        if ($n < 1) {
            $this->fail('empty');
        }

        $computed = [
            'accuracy' => ($cm['tp'] + $cm['tn']) / $n,
            'precision' => ($cm['tp'] + $cm['fp']) > 0 ? $cm['tp'] / ($cm['tp'] + $cm['fp']) : 0.0,
            'recall' => ($cm['tp'] + $cm['fn']) > 0 ? $cm['tp'] / ($cm['tp'] + $cm['fn']) : 0.0,
            'false_positive_rate' => ($cm['fp'] + $cm['tn']) > 0 ? $cm['fp'] / ($cm['fp'] + $cm['tn']) : 0.0,
            'false_negative_rate' => ($cm['fn'] + $cm['tp']) > 0 ? $cm['fn'] / ($cm['fn'] + $cm['tp']) : 0.0,
        ];
        $computed['f1'] = ($computed['precision'] + $computed['recall']) > 0
            ? 2 * $computed['precision'] * $computed['recall'] / ($computed['precision'] + $computed['recall'])
            : 0.0;

        foreach ($computed as $key => $value) {
            if (! isset($data[$key]) || ! is_numeric($data[$key]) || abs($data[$key] - $value) > self::TOLERANCE) {
                $this->fail('inconsistent');
            }
        }

        return $computed + [
            'confusion_matrix' => $cm,
            'n_samples' => $n,
            'threshold' => isset($data['threshold']) ? (float) $data['threshold'] : null,
            'split' => (string) ($data['split'] ?? 'test'),
        ] + Arr::only($data, ['auc', 'n_failed', 'n_validation', 'n_test', 'class_balance_test', 'operating_points', 'platform_three_class', 'seconds_per_sample_mean']);
    }

    /**
     * يتحقق من التقرير ويدمجه ضمن مقاييس نسخة النموذج، دون أن يطغى على تقييمات سابقة لمجموعات بيانات أخرى.
     * نموذج مركّب واحد (كنموذج الصور الذي يجمع كاشف وجوه وكاشف توليد) قد يُقاس على أكثر من مجموعة بيانات
     * منفصلة تفحص قدرات مختلفة له؛ لذلك تُخزَّن المقاييس مفهرسة بمعرّف مجموعة البيانات لا مستبدَلة كليًا.
     */
    public function import(ModelVersion $model, array $report): array
    {
        $metrics = $this->validate($report);

        $datasetKey = (string) ($report['dataset_key'] ?? 'default');
        $byDataset = (array) (($model->metrics ?? [])['by_dataset'] ?? []);
        $byDataset[$datasetKey] = $metrics + ['dataset_label' => (string) ($report['dataset'] ?? $datasetKey)];

        $labels = array_column($byDataset, 'dataset_label');

        $model->forceFill([
            'metrics' => ['by_dataset' => $byDataset],
            'evaluation_dataset' => mb_substr(implode(' · ', $labels), 0, 250) ?: null,
            'evaluated_at' => now(),
        ])->save();

        return $metrics;
    }

    private function fail(string $reason): never
    {
        throw ValidationException::withMessages(['metrics_file' => [__('admin.models.import_errors.'.$reason)]]);
    }
}
