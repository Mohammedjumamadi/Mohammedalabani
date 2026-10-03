<?php

namespace App\Services\Media;

use App\Enums\MediaType;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * يستخرج المعلومات التقنية الأساسية للملف. أي فشل هنا لا يوقف الرفع، فالحقول تبقى فارغة.
 */
class MediaProbe
{
    /** @return array{width: ?int, height: ?int, duration_seconds: ?float, metadata: array<string, mixed>} */
    public function probe(string $path, MediaType $type): array
    {
        return match ($type) {
            MediaType::Image => $this->probeImage($path),
            default => $this->probeWithFfprobe($path, $type),
        };
    }

    private function probeImage(string $path): array
    {
        $info = @getimagesize($path);
        $meta = ['format' => $info['mime'] ?? null];

        // بيانات EXIF المهمة فقط (بدون إحداثيات GPS حفاظًا على الخصوصية)
        if (function_exists('exif_read_data') && ($info['mime'] ?? '') === 'image/jpeg') {
            $exif = @exif_read_data($path, 'IFD0', false) ?: [];
            foreach (['Make', 'Model', 'Software', 'DateTime', 'Orientation'] as $key) {
                if (isset($exif[$key]) && is_scalar($exif[$key])) {
                    $meta['exif'][$key] = mb_substr(trim((string) $exif[$key]), 0, 100);
                }
            }
        }

        return [
            'width' => $info[0] ?? null,
            'height' => $info[1] ?? null,
            'duration_seconds' => null,
            'metadata' => $meta,
        ];
    }

    private function probeWithFfprobe(string $path, MediaType $type): array
    {
        $empty = ['width' => null, 'height' => null, 'duration_seconds' => null, 'metadata' => []];

        $binary = $this->ffprobeBinary();
        if ($binary === null) {
            return $empty;
        }

        try {
            $process = new Process([$binary, '-v', 'error', '-print_format', 'json', '-show_format', '-show_streams', $path]);
            $process->setTimeout(30)->run();

            if (! $process->isSuccessful()) {
                return $empty;
            }

            $data = json_decode($process->getOutput(), true) ?: [];
            $streams = $data['streams'] ?? [];
            $video = collect($streams)->firstWhere('codec_type', 'video');
            $audio = collect($streams)->firstWhere('codec_type', 'audio');

            $meta = [
                'format' => $data['format']['format_name'] ?? null,
                'bit_rate' => isset($data['format']['bit_rate']) ? (int) $data['format']['bit_rate'] : null,
            ];
            if ($video && $type === MediaType::Video) {
                $meta['video_codec'] = $video['codec_name'] ?? null;
                $meta['fps'] = $this->fps($video['avg_frame_rate'] ?? null);
            }
            if ($audio) {
                $meta['audio_codec'] = $audio['codec_name'] ?? null;
                $meta['sample_rate'] = isset($audio['sample_rate']) ? (int) $audio['sample_rate'] : null;
                $meta['channels'] = $audio['channels'] ?? null;
            }
            $meta['has_audio'] = $audio !== null;

            return [
                'width' => $type === MediaType::Video ? ($video['width'] ?? null) : null,
                'height' => $type === MediaType::Video ? ($video['height'] ?? null) : null,
                'duration_seconds' => isset($data['format']['duration']) ? round((float) $data['format']['duration'], 2) : null,
                'metadata' => array_filter($meta, fn ($v) => $v !== null),
            ];
        } catch (\Throwable $e) {
            Log::warning('ffprobe failed', ['error' => $e->getMessage()]);

            return $empty;
        }
    }

    private function ffprobeBinary(): ?string
    {
        $configured = config('aimv.media.ffprobe');

        if ($configured && is_file($configured)) {
            return $configured;
        }

        return (new ExecutableFinder)->find($configured ?: 'ffprobe') ?? $this->findInWinGetPackages();
    }

    /** winget يثبّت FFmpeg في مجلد خاص قد لا يكون ضمن PATH لعمليات قائمة. */
    private function findInWinGetPackages(): ?string
    {
        $base = getenv('LOCALAPPDATA');
        if (! $base) {
            return null;
        }

        $matches = glob($base.'/Microsoft/WinGet/Packages/Gyan.FFmpeg*/ffmpeg-*/bin/ffprobe.exe') ?: [];

        return $matches[0] ?? null;
    }

    private function fps(?string $rate): ?float
    {
        if (! $rate || ! str_contains($rate, '/')) {
            return null;
        }

        [$n, $d] = array_map('floatval', explode('/', $rate));

        return $d > 0 ? round($n / $d, 2) : null;
    }
}
