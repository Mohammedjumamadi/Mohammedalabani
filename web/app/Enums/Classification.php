<?php

namespace App\Enums;

enum Classification: string
{
    case Real = 'real';
    case Uncertain = 'uncertain';
    case Fake = 'fake';

    /**
     * يصنّف النتيجة بحسب احتمال التلاعب والحدود المضبوطة من لوحة المدير.
     *  - احتمال التلاعب ≥ $fakeThreshold : مزيف على الأرجح
     *  - احتمال التلاعب <  $realThreshold: حقيقي على الأرجح
     *  - ما بينهما                       : غير حاسم ويحتاج إلى مراجعة بشرية
     */
    public static function fromManipulationProbability(float $probability, float $fakeThreshold, float $realThreshold): self
    {
        if ($probability >= $fakeThreshold) {
            return self::Fake;
        }

        if ($probability < $realThreshold) {
            return self::Real;
        }

        return self::Uncertain;
    }

    public function label(): string
    {
        return __('enums.classification.'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::Real => 'bi-check-circle-fill',
            self::Uncertain => 'bi-exclamation-triangle-fill',
            self::Fake => 'bi-x-octagon-fill',
        };
    }

    public function cssClass(): string
    {
        return 'verdict-'.$this->value;
    }

    public function riskLevel(): RiskLevel
    {
        return match ($this) {
            self::Real => RiskLevel::Low,
            self::Uncertain => RiskLevel::Medium,
            self::Fake => RiskLevel::High,
        };
    }

    /** الحالة النهائية للطلب بعد اكتمال التحليل. */
    public function requestStatus(): RequestStatus
    {
        return $this === self::Uncertain ? RequestStatus::NeedsReview : RequestStatus::Completed;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
