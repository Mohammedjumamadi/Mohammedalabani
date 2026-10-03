<?php

namespace App\Models;

use Database\Factories\SharedReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SharedReport extends Model
{
    /** @use HasFactory<SharedReportFactory> */
    use HasFactory;

    protected $fillable = [
        'verification_request_id',
        'user_id',
        'token_hash',
        'token_encrypted',
        'hide_owner',
        'show_preview',
        'views_count',
        'expires_at',
        'revoked_at',
    ];

    protected $hidden = ['token_hash', 'token_encrypted'];

    protected function casts(): array
    {
        return [
            'token_encrypted' => 'encrypted',
            'hide_owner' => 'boolean',
            'show_preview' => 'boolean',
            'views_count' => 'integer',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function verificationRequest(): BelongsTo
    {
        return $this->belongsTo(VerificationRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * ينشئ رابط مشاركة جديدًا برمز عشوائي طوله 48 خانة (~285 بت) لا يمكن تخمينه.
     * يُخزَّن الرمز مشفّرًا مع بصمة SHA-256 للبحث، ولا يُحفظ نصًا واضحًا.
     */
    public static function issue(VerificationRequest $request, User $owner, bool $hideOwner = true, bool $showPreview = false, ?\DateTimeInterface $expiresAt = null): self
    {
        $token = Str::random(48);

        return static::create([
            'verification_request_id' => $request->id,
            'user_id' => $owner->id,
            'token_hash' => hash('sha256', $token),
            'token_encrypted' => $token,
            'hide_owner' => $hideOwner,
            'show_preview' => $showPreview,
            'expires_at' => $expiresAt,
        ]);
    }

    /** يبحث عن رابط نشط (غير ملغى وغير منتهٍ) بواسطة الرمز الخام. */
    public static function findActiveByToken(string $token): ?self
    {
        $shared = static::query()->where('token_hash', hash('sha256', $token))->first();

        return $shared?->isActive() ? $shared : null;
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => now()])->save();
    }

    public function token(): string
    {
        return $this->token_encrypted;
    }

    public function publicUrl(): string
    {
        return route('reports.public', $this->token());
    }
}
