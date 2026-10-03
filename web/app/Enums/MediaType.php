<?php

namespace App\Enums;

enum MediaType: string
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';

    public function label(): string
    {
        return __('enums.media_type.'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::Image => 'bi-image',
            self::Video => 'bi-camera-video',
            self::Audio => 'bi-mic',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
