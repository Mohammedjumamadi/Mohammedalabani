<?php

namespace App\Services\Verification;

use App\Enums\MediaType;
use App\Models\VerificationResult;

/**
 * يبني شرحًا مبسطًا للنتيجة من التصنيف والمؤشرات المكتشفة فعلًا (لا من نص ثابت).
 * يُستدعى بلغة محددة ليتبع الشرح لغة الواجهة عند العرض.
 */
class ExplanationBuilder
{
    public function build(VerificationResult $result, MediaType $type, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $probability = number_format($result->manipulation_probability, 1);

        $intro = __('explanation.'.$result->classification->value, [
            'media' => mb_strtolower(__('explanation.media.'.$type->value, [], $locale)),
            'probability' => $probability,
        ], $locale);

        $top = $result->indicators
            ->whereIn('severity', ['high', 'medium'])
            ->sortByDesc('score')
            ->take(3);

        if ($top->isNotEmpty()) {
            $titles = $top->map(fn ($i) => __('indicators.'.$i->code.'.title', [], $locale))->implode(app()->getLocale() === 'ar' || $locale === 'ar' ? '، ' : ', ');
            $intro .= ' '.__('explanation.indicators', ['list' => $titles], $locale);
        }

        return $intro.' '.__('explanation.limits', [], $locale);
    }
}
