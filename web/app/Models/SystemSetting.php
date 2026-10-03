<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    private const CACHE_KEY = 'system_settings.all';

    protected $fillable = ['key', 'value', 'type', 'group'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /** قيمة الإعداد بعد تحويلها إلى نوعها الصحيح. */
    public function typedValue(): mixed
    {
        return match ($this->type) {
            'int' => (int) $this->value,
            'float' => (float) $this->value,
            'bool' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode((string) $this->value, true),
            default => $this->value,
        };
    }

    /** @return array<string, mixed> */
    public static function allValues(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return static::query()->get()->mapWithKeys(fn (self $s) => [$s->key => $s->typedValue()])->all();
        });
    }

    /**
     * يقرأ إعدادًا؛ وإذا لم يوجد يعود إلى $default ثم إلى القيمة الافتراضية في config/aimv.php.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::allValues();

        if (array_key_exists($key, $all)) {
            return $all[$key];
        }

        return $default ?? config('aimv.defaults.'.$key);
    }

    public static function put(string $key, mixed $value, ?string $type = null, string $group = 'general'): self
    {
        $type ??= match (true) {
            is_bool($value) => 'bool',
            is_int($value) => 'int',
            is_float($value) => 'float',
            is_array($value) => 'json',
            default => 'string',
        };

        $stored = match ($type) {
            'bool' => $value ? '1' : '0',
            'json' => json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };

        return static::updateOrCreate(['key' => $key], ['value' => $stored, 'type' => $type, 'group' => $group]);
    }
}
