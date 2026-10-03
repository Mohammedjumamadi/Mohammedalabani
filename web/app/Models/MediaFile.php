<?php

namespace App\Models;

use App\Enums\MediaType;
use Database\Factories\MediaFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class MediaFile extends Model
{
    /** @use HasFactory<MediaFileFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'media_type',
        'original_name',
        'stored_name',
        'disk',
        'path',
        'mime_type',
        'extension',
        'size_bytes',
        'sha256',
        'width',
        'height',
        'duration_seconds',
        'metadata',
        'consented_at',
        'expires_at',
        'file_deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'media_type' => MediaType::class,
            'metadata' => 'array',
            'size_bytes' => 'integer',
            'duration_seconds' => 'float',
            'consented_at' => 'datetime',
            'expires_at' => 'datetime',
            'file_deleted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verificationRequest(): HasOne
    {
        return $this->hasOne(VerificationRequest::class);
    }

    /** هل الملف الفعلي ما زال موجودًا على القرص؟ */
    public function existsOnDisk(): bool
    {
        return $this->path !== null
            && $this->file_deleted_at === null
            && Storage::disk($this->disk)->exists($this->path);
    }

    public function humanSize(): string
    {
        $bytes = (float) $this->size_bytes;
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024 || $unit === 'GB') {
                return ($unit === 'B' ? (string) (int) $bytes : number_format($bytes, 2)).' '.$unit;
            }
            $bytes /= 1024;
        }

        return '';
    }
}
