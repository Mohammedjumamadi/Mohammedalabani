<?php

namespace Tests\Unit;

use App\Services\Evaluation\MetricsImporter;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MetricsImporterTest extends TestCase
{
    /** مصفوفة ارتباك ومقاييس مشتقة منها يدويًا: tp=45 fn=5 fp=8 tn=42 (n=100) */
    private function consistent(): array
    {
        return [
            'confusion_matrix' => ['tp' => 45, 'fp' => 8, 'tn' => 42, 'fn' => 5],
            'accuracy' => 0.87,
            'precision' => 45 / 53,
            'recall' => 0.9,
            'false_positive_rate' => 0.16,
            'false_negative_rate' => 0.1,
            'f1' => 2 * (45 / 53) * 0.9 / ((45 / 53) + 0.9),
        ];
    }

    public function test_consistent_metrics_are_accepted_and_recomputed(): void
    {
        $metrics = (new MetricsImporter)->validate($this->consistent());

        $this->assertEqualsWithDelta(0.87, $metrics['accuracy'], 1e-9);
        $this->assertSame(100, $metrics['n_samples']);
    }

    public function test_metrics_that_do_not_match_the_confusion_matrix_are_rejected(): void
    {
        $fabricated = $this->consistent();
        $fabricated['accuracy'] = 0.97; // رقم أجمل من الحقيقة

        $this->expectException(ValidationException::class);
        (new MetricsImporter)->validate($fabricated);
    }

    public function test_missing_or_invalid_confusion_matrix_is_rejected(): void
    {
        foreach ([[], ['confusion_matrix' => ['tp' => 1]], ['confusion_matrix' => ['tp' => -1, 'fp' => 0, 'tn' => 0, 'fn' => 0]], ['confusion_matrix' => ['tp' => 0, 'fp' => 0, 'tn' => 0, 'fn' => 0]]] as $payload) {
            try {
                (new MetricsImporter)->validate($payload);
                $this->fail('كان يجب رفض المدخل');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
