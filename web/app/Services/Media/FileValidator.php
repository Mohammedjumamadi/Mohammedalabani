<?php

namespace App\Services\Media;

use App\Enums\MediaType;
use App\Models\SystemSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class FileValidator
{
    /** بدايات ملفات تنفيذية أو سكربتات، تُرفض حتى لو كان الامتداد مقبولًا. */
    private const DANGEROUS_SIGNATURES = [
        "MZ" => 'exe',
        "\x7FELF" => 'elf',
        '#!' => 'script',
        '<?php' => 'php',
        '<?=' => 'php',
        '<script' => 'html',
        '<html' => 'html',
        '<!DOC' => 'html',
        '<svg' => 'svg',
        "PK\x03\x04" => 'zip',
    ];

    /** @return list<string> */
    public function allowedExtensions(MediaType $type): array
    {
        $supported = config("aimv.media.types.{$type->value}.extensions");
        $configured = SystemSetting::get('allowed_extensions')[$type->value] ?? $supported;
        $blocked = config('aimv.media.blocked_extensions');

        return array_values(array_diff(
            array_intersect(array_map('strtolower', $configured), $supported),
            $blocked,
        ));
    }

    /** الحد الأقصى المسموح فعليًا بالبايت: أقل قيمتين بين إعداد المدير وحد PHP على الخادم. */
    public function maxBytes(): int
    {
        return min($this->configuredMaxBytes(), $this->serverLimitBytes());
    }

    public function configuredMaxBytes(): int
    {
        return max(1, (int) SystemSetting::get('max_upload_mb')) * 1024 * 1024;
    }

    public function serverLimitBytes(): int
    {
        $limits = array_filter([
            $this->iniBytes((string) ini_get('upload_max_filesize')),
            $this->iniBytes((string) ini_get('post_max_size')),
        ]);

        return $limits ? min($limits) : PHP_INT_MAX;
    }

    /**
     * يفحص الملف من محتواه لا من اسمه فقط. يرمي ValidationException برسالة مفهومة عند الرفض.
     *
     * @return array{extension: string, mime: string}
     */
    public function validate(UploadedFile $file, MediaType $type): array
    {
        if (! $file->isValid()) {
            $this->fail(match ($file->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'too_large_server',
                UPLOAD_ERR_PARTIAL => 'partial',
                default => 'upload_failed',
            });
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $this->assertSafeName($file->getClientOriginalName());

        if (in_array($extension, config('aimv.media.blocked_extensions'), true)
            || ! in_array($extension, $this->allowedExtensions($type), true)) {
            $this->fail('extension', ['types' => strtoupper(implode(', ', $this->allowedExtensions($type)))]);
        }

        if ($file->getSize() > $this->maxBytes()) {
            $this->fail('too_large', ['max' => $this->formatBytes($this->maxBytes())]);
        }

        if ($file->getSize() === 0) {
            $this->fail('empty');
        }

        $this->assertNotExecutable($file->getRealPath());

        // النوع يُقرأ من محتوى الملف عبر fileinfo، ولا يُوثق بما يرسله المتصفح
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        if (! in_array($mime, config("aimv.media.types.{$type->value}.mimes"), true)) {
            $this->fail('mime', ['type' => $type->label()]);
        }

        if ($type === MediaType::Image) {
            $this->assertValidImage($file->getRealPath());
        }

        return ['extension' => $extension === 'jpeg' ? 'jpg' : $extension, 'mime' => $mime];
    }

    private function assertSafeName(string $name): void
    {
        // رفض أسماء الملفات التي تحتوي على امتداد تنفيذي مخفي مثل shell.php.jpg
        $parts = array_slice(explode('.', strtolower($name)), 1, -1);
        foreach ($parts as $part) {
            if (in_array($part, config('aimv.media.blocked_extensions'), true)) {
                $this->fail('double_extension');
            }
        }

        if (str_contains($name, "\0")) {
            $this->fail('extension', ['types' => '']);
        }
    }

    private function assertNotExecutable(string $path): void
    {
        $head = (string) file_get_contents($path, false, null, 0, 512);
        $headLower = strtolower($head);

        foreach (self::DANGEROUS_SIGNATURES as $signature => $kind) {
            if (str_starts_with($headLower, strtolower($signature))) {
                $this->fail('executable');
            }
        }

        if (str_contains($headLower, '<?php')) {
            $this->fail('executable');
        }
    }

    private function assertValidImage(string $path): void
    {
        $info = @getimagesize($path);

        if ($info === false) {
            $this->fail('corrupt');
        }

        if ($info[0] * $info[1] > config('aimv.media.max_image_pixels')) {
            $this->fail('image_too_big');
        }
    }

    private function fail(string $key, array $replace = []): never
    {
        throw ValidationException::withMessages(['file' => [__('upload.errors.'.$key, $replace)]]);
    }

    private function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '0') {
            return 0;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    public function formatBytes(int $bytes): string
    {
        return $bytes >= 1024 ** 3
            ? number_format($bytes / 1024 ** 3, 1).' GB'
            : number_format($bytes / 1024 ** 2, 0).' MB';
    }
}
