<?php

namespace App\Modules\Settings;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::rememberForever("setting.{$key}", fn () => static::where('key', $key)->first()?->value);

        return $value ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting.{$key}");
    }

    /** Reply is due within after_hours of assignment; auto-reminder only when auto_enabled. */
    public static function reminderRules(): array
    {
        return array_merge(
            ['auto_enabled' => false, 'after_hours' => 2, 'whatsapp_alert' => false],
            static::get('reminder_rules', []),
        );
    }

    public static function isAgencyNumber(string $phone): bool
    {
        return in_array($phone, static::get('agency_numbers', []), true);
    }
}
