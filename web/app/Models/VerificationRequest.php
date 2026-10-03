<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Database\Factories\VerificationRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class VerificationRequest extends Model
{
    /** @use HasFactory<VerificationRequestFactory> */
    use HasFactory, SoftDeletes;

    /** أحرف الرقم المرجعي بلا ما يلتبس (0/O و1/I). */
    private const REFERENCE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    protected $fillable = [
        'reference_number',
        'user_id',
        'media_file_id',
        'status',
        'progress',
        'stage',
        'reused_from_id',
        'error_message',
        'attempts',
        'is_demo',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'progress' => 'integer',
            'attempts' => 'integer',
            'is_demo' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $request) {
            $request->reference_number ??= static::generateReference();
        });
    }

    /** رقم مرجعي فريد للتقرير مثل AMV-2026-K7H2Q9XM. */
    public static function generateReference(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= self::REFERENCE_ALPHABET[random_int(0, strlen(self::REFERENCE_ALPHABET) - 1)];
            }
            $reference = 'AMV-'.date('Y').'-'.$code;
        } while (static::withTrashed()->where('reference_number', $reference)->exists());

        return $reference;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class)->withTrashed();
    }

    public function result(): HasOne
    {
        return $this->hasOne(VerificationResult::class);
    }

    public function reusedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reused_from_id')->withTrashed();
    }

    public function sharedReports(): HasMany
    {
        return $this->hasMany(SharedReport::class);
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeWithStatus(Builder $query, RequestStatus ...$statuses): Builder
    {
        return $query->whereIn('status', array_map(fn (RequestStatus $s) => $s->value, $statuses));
    }

    public function getRouteKeyName(): string
    {
        return 'reference_number';
    }

    public function shortReference(): string
    {
        return Str::upper($this->reference_number);
    }
}
