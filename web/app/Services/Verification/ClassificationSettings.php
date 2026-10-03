<?php

namespace App\Services\Verification;

use App\Enums\Classification;
use App\Models\SystemSetting;

/** حدود التصنيف الحالية كما يضبطها المدير، مع ضمان أن حد «حقيقي» أقل دائمًا من حد «مزيف». */
class ClassificationSettings
{
    /** @return array{fake: float, real: float} */
    public function thresholds(): array
    {
        $fake = (float) SystemSetting::get('threshold_fake');
        $real = (float) SystemSetting::get('threshold_real');

        if ($real >= $fake) {
            $fake = (float) config('aimv.defaults.threshold_fake');
            $real = (float) config('aimv.defaults.threshold_real');
        }

        return ['fake' => $fake, 'real' => $real];
    }

    public function classify(float $manipulationProbability): Classification
    {
        $t = $this->thresholds();

        return Classification::fromManipulationProbability($manipulationProbability, $t['fake'], $t['real']);
    }
}
