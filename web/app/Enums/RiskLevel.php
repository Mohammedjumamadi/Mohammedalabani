<?php

namespace App\Enums;

enum RiskLevel: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return __('enums.risk_level.'.$this->value);
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Low => 'verdict-real',
            self::Medium => 'verdict-uncertain',
            self::High => 'verdict-fake',
        };
    }
}
