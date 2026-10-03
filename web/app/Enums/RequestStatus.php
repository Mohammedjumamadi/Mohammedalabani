<?php

namespace App\Enums;

enum RequestStatus: string
{
    case Pending = 'pending';
    case Uploading = 'uploading';
    case Analyzing = 'analyzing';
    case Completed = 'completed';
    case Failed = 'failed';
    case NeedsReview = 'needs_review';

    public function label(): string
    {
        return __('enums.request_status.'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'bi-hourglass-split',
            self::Uploading => 'bi-cloud-arrow-up',
            self::Analyzing => 'bi-cpu',
            self::Completed => 'bi-check-circle-fill',
            self::Failed => 'bi-x-octagon-fill',
            self::NeedsReview => 'bi-person-check',
        };
    }

    /** صنف CSS لشارة الحالة (يقترن دائمًا بنص وأيقونة). */
    public function cssClass(): string
    {
        return match ($this) {
            self::Completed => 'verdict-real',
            self::NeedsReview => 'verdict-uncertain',
            self::Failed => 'verdict-fake',
            default => 'verdict-neutral',
        };
    }

    /** الحالات التي انتهت فيها المعالجة ولا يتوقع تغيّرها تلقائيًا. */
    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::NeedsReview], true);
    }

    /** الحالات التي تحمل نتيجة تحليل صالحة للعرض. */
    public function hasResult(): bool
    {
        return in_array($this, [self::Completed, self::NeedsReview], true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
