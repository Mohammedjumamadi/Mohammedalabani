<?php

namespace Tests\Unit;

use App\Enums\Classification;
use App\Enums\RequestStatus;
use App\Enums\RiskLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ClassificationTest extends TestCase
{
    /** @return array<string, array{float, Classification}> */
    public static function defaultThresholdCases(): array
    {
        return [
            'zero' => [0.0, Classification::Real],
            'just below 30' => [29.99, Classification::Real],
            'exactly 30' => [30.0, Classification::Uncertain],
            'middle' => [50.0, Classification::Uncertain],
            'just below 70' => [69.99, Classification::Uncertain],
            'exactly 70' => [70.0, Classification::Fake],
            'high' => [95.5, Classification::Fake],
            'hundred' => [100.0, Classification::Fake],
        ];
    }

    #[DataProvider('defaultThresholdCases')]
    public function test_it_classifies_with_default_thresholds(float $manipulation, Classification $expected): void
    {
        $this->assertSame($expected, Classification::fromManipulationProbability($manipulation, 70, 30));
    }

    public function test_it_respects_custom_thresholds(): void
    {
        // المدير يشدد الحدود: مزيف ≥ 50 وحقيقي < 10
        $this->assertSame(Classification::Fake, Classification::fromManipulationProbability(50, 50, 10));
        $this->assertSame(Classification::Uncertain, Classification::fromManipulationProbability(49.9, 50, 10));
        $this->assertSame(Classification::Real, Classification::fromManipulationProbability(9.9, 50, 10));
    }

    public function test_risk_level_follows_classification(): void
    {
        $this->assertSame(RiskLevel::Low, Classification::Real->riskLevel());
        $this->assertSame(RiskLevel::Medium, Classification::Uncertain->riskLevel());
        $this->assertSame(RiskLevel::High, Classification::Fake->riskLevel());
    }

    public function test_only_inconclusive_results_need_human_review(): void
    {
        $this->assertSame(RequestStatus::Completed, Classification::Real->requestStatus());
        $this->assertSame(RequestStatus::Completed, Classification::Fake->requestStatus());
        $this->assertSame(RequestStatus::NeedsReview, Classification::Uncertain->requestStatus());
    }

    public function test_every_classification_has_an_icon_and_css_class(): void
    {
        foreach (Classification::cases() as $case) {
            $this->assertNotEmpty($case->icon(), 'التصنيف يجب ألا يعتمد على اللون وحده');
            $this->assertSame('verdict-'.$case->value, $case->cssClass());
        }
    }

    public function test_request_status_final_and_result_states(): void
    {
        $this->assertFalse(RequestStatus::Pending->isFinal());
        $this->assertFalse(RequestStatus::Uploading->isFinal());
        $this->assertFalse(RequestStatus::Analyzing->isFinal());
        $this->assertTrue(RequestStatus::Completed->isFinal());
        $this->assertTrue(RequestStatus::Failed->isFinal());
        $this->assertTrue(RequestStatus::NeedsReview->isFinal());

        $this->assertTrue(RequestStatus::Completed->hasResult());
        $this->assertTrue(RequestStatus::NeedsReview->hasResult());
        $this->assertFalse(RequestStatus::Failed->hasResult());
    }
}
