<?php

namespace App\Enums;

enum IndicatorSeverity: string
{
    case Info = 'info';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return __('enums.severity.'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::Info => 'bi-info-circle-fill',
            self::Low => 'bi-dash-circle-fill',
            self::Medium => 'bi-exclamation-triangle-fill',
            self::High => 'bi-x-octagon-fill',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Info => 'verdict-neutral',
            self::Low => 'verdict-real',
            self::Medium => 'verdict-uncertain',
            self::High => 'verdict-fake',
        };
    }
}
