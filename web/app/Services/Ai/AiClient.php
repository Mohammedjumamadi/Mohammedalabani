<?php

namespace App\Services\Ai;

use App\Models\MediaFile;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * عميل REST لخدمة الذكاء الاصطناعي (FastAPI). العقد الكامل موثق في docs/API.md.
 */
class AiClient
{
    public function analyze(MediaFile $media): AiAnalysis
    {
        $path = Storage::disk($media->disk)->path((string) $media->path);
        $stream = @fopen($path, 'rb');

        if ($stream === false) {
            throw new AiServiceException(AiServiceException::UNAVAILABLE, "Cannot open media file {$media->id}");
        }

        try {
            $response = $this->http()
                ->timeout(config('aimv.ai.timeout'))
                ->attach('file', $stream, 'upload.'.$media->extension)
                ->post('/v1/analyze', ['media_type' => $media->media_type->value]);
        } catch (ConnectionException $e) {
            $timedOut = str_contains(strtolower($e->getMessage()), 'timed out');
            Log::error('AI service connection failed', ['media_id' => $media->id, 'error' => $e->getMessage()]);

            throw new AiServiceException(
                $timedOut ? AiServiceException::TIMEOUT : AiServiceException::UNAVAILABLE,
                $e->getMessage(),
                retryable: true,
            );
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($response->status() === 503) {
            throw new AiServiceException(AiServiceException::NO_MODEL, 'AI service has no model for this type', retryable: false);
        }

        if ($response->failed()) {
            Log::error('AI service returned an error', ['media_id' => $media->id, 'status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)]);

            throw new AiServiceException(
                $response->clientError() ? AiServiceException::REJECTED : AiServiceException::UNAVAILABLE,
                'HTTP '.$response->status(),
                retryable: $response->serverError(),
            );
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new AiServiceException(AiServiceException::INVALID_RESPONSE, 'Non-JSON response');
        }

        return AiAnalysis::fromArray($json);
    }

    /**
     * حالة الخدمة والنماذج المحمّلة. تُخزَّن لثوانٍ معدودة لتخفيف الضغط عند تكرار الاستدعاء.
     *
     * @return array{online: bool, data: ?array<string, mixed>, error: ?string}
     */
    public function health(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget('ai.health');
        }

        return Cache::remember('ai.health', 10, function () {
            try {
                $response = $this->http()->timeout(5)->get('/health');

                if ($response->successful() && is_array($response->json())) {
                    return ['online' => true, 'data' => $response->json(), 'error' => null];
                }

                return ['online' => false, 'data' => null, 'error' => 'HTTP '.$response->status()];
            } catch (\Throwable $e) {
                return ['online' => false, 'data' => null, 'error' => 'unreachable'];
            }
        });
    }

    /**
     * النماذج مفتوحة المصدر المسجَّلة في خدمة الذكاء الاصطناعي (الاسم والمصدر والترخيص).
     *
     * @return list<array<string, mixed>>
     */
    public function models(): array
    {
        return Cache::remember('ai.models', 30, function () {
            try {
                $response = $this->http()->timeout(8)->get('/models');

                $json = $response->successful() ? $response->json() : [];

                // لا نثق بشكل الاستجابة: نُبقي العناصر المكتملة فقط حتى لا تنهار لوحة المدير
                return array_values(array_filter(
                    is_array($json) ? $json : [],
                    fn ($item) => is_array($item) && isset($item['name'], $item['media_type'], $item['license'], $item['source'], $item['description']) && array_key_exists('loaded', $item),
                ));
            } catch (\Throwable $e) {
                return [];
            }
        });
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('aimv.ai.url'), '/'))
            ->withHeaders(['X-API-Key' => (string) config('aimv.ai.key')])
            ->acceptJson();
    }
}
