<?php

namespace Tests\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * يحاكي خدمة الذكاء الاصطناعي في الاختبارات فقط (عقد الاستجابة موثق في docs/API.md).
 * هذه ليست نتائج نموذج حقيقي، بل مدخلات اختبار لمنطق المنصة.
 */
trait FakesAiService
{
    protected function setUpFakeStorage(): void
    {
        Storage::fake('private');
    }

    /** @param array<string, mixed> $override */
    protected function fakeAnalysis(float $manipulation = 82.0, string $type = 'image', array $override = []): array
    {
        return array_replace_recursive([
            'media_type' => $type,
            'manipulation_probability' => $manipulation,
            'authenticity_probability' => 100 - $manipulation,
            'confidence' => max($manipulation, 100 - $manipulation),
            'model' => ['name' => 'test/fake-detector', 'version' => '1.2.3', 'source' => 'https://example.test/model', 'license' => 'apache-2.0'],
            'processing_time' => 2.5,
            'indicators' => [
                ['code' => 'model_deepfake_score', 'category' => 'model', 'severity' => 'high', 'score' => $manipulation],
                ['code' => 'ela_anomaly', 'category' => 'compression', 'severity' => 'medium', 'score' => 55],
            ],
            'technical' => ['frames_analyzed' => 1],
        ], $override);
    }

    protected function fakeAiReturns(array $analysis): void
    {
        Http::swap(new HttpFactory);
        Http::fake([
            '*/v1/analyze' => Http::response($analysis, 200),
            '*/health' => Http::response(['status' => 'ok', 'models' => []], 200),
        ]);
    }

    protected function fakeAiDown(): void
    {
        Http::swap(new HttpFactory);
        Http::fake(fn () => throw new ConnectionException('Connection refused'));
    }

    /** JPEG صالح حقيقي بمحتوى مختلف في كل استدعاء (لبصمات مختلفة). */
    protected function jpegBytes(int $seed = 1): string
    {
        $image = imagecreatetruecolor(64, 64);
        imagefill($image, 0, 0, imagecolorallocate($image, ($seed * 37) % 255, ($seed * 91) % 255, ($seed * 53) % 255));
        ob_start();
        imagejpeg($image, null, 90);
        imagedestroy($image);

        return (string) ob_get_clean();
    }
}
