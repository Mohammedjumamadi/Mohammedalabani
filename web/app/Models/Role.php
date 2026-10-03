<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    public const ADMIN = 'admin';

    public const USER = 'user';

    /** الأدوار المعتمدة في المنصة وأسماؤها [عربي, إنجليزي]. */
    public const DEFINITIONS = [
        self::ADMIN => ['مدير النظام', 'Administrator'],
        self::USER => ['مستخدم مسجل', 'Registered user'],
    ];

    protected $fillable = ['slug', 'name_ar', 'name_en'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function label(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }

    /** يعيد الدور المعتمد وينشئه إن لم يكن موجودًا (لا تتعطل المنصة إن لم تُشغَّل البذور). */
    public static function ensure(string $slug): self
    {
        [$ar, $en] = self::DEFINITIONS[$slug];

        return static::firstOrCreate(['slug' => $slug], ['name_ar' => $ar, 'name_en' => $en]);
    }

    public static function idFor(string $slug): int
    {
        return static::ensure($slug)->id;
    }
}
