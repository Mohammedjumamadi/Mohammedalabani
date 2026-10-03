<?php

namespace App\Models;

use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * role_id وstatus غير قابلين للتعبئة الجماعية عمدًا حتى لا يمكن رفع الصلاحيات عبر طلب مزوّر.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'locale',
        'phone',
        'city',
        'organization',
        'account_type',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function mediaFiles(): HasMany
    {
        return $this->hasMany(MediaFile::class);
    }

    public function verificationRequests(): HasMany
    {
        return $this->hasMany(VerificationRequest::class);
    }

    public function sharedReports(): HasMany
    {
        return $this->hasMany(SharedReport::class);
    }

    public function userNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function isAdmin(): bool
    {
        return $this->role?->slug === Role::ADMIN;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /** حساب داخلي غير قابل لتسجيل الدخول، تُنسب إليه تحليلات الزوار غير المسجَّلين. */
    public function isGuestSystem(): bool
    {
        return $this->account_type === self::GUEST_ACCOUNT_TYPE;
    }

    public const GUEST_ACCOUNT_TYPE = 'guest_system';

    public const GUEST_EMAIL = 'guest@aimv.internal';

    /**
     * يعيد الحساب النظامي الذي تُنسب إليه ملفات الزوار، وينشئه إن لم يكن موجودًا.
     * role_id وstatus خارج $fillable عمدًا، فتُضبط هنا بإسناد مباشر لا عبر التعبئة الجماعية.
     */
    public static function guestSystem(): self
    {
        if ($existing = static::where('email', self::GUEST_EMAIL)->first()) {
            return $existing;
        }

        $user = new self([
            'name' => 'زائر النظام',
            'email' => self::GUEST_EMAIL,
            'password' => Str::random(64),
            'locale' => 'ar',
            'account_type' => self::GUEST_ACCOUNT_TYPE,
        ]);
        $user->role_id = Role::idFor(Role::USER);
        $user->status = UserStatus::Suspended;
        $user->save();

        return $user;
    }
}
