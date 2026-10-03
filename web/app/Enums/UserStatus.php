<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return __('enums.user_status.'.$this->value);
    }

    public function cssClass(): string
    {
        return $this === self::Active ? 'verdict-real' : 'verdict-fake';
    }
}
