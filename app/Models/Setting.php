<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group'];

    protected function casts(): array
    {
        return [
            'value' => 'string',
        ];
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        if (!$setting) return $default;
        $val = $setting->value;
        $decoded = json_decode($val, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $val;
    }

    public static function setValue(string $key, mixed $value, string $group = 'general'): void
    {
        $val = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
        static::updateOrCreate(['key' => $key], ['value' => $val, 'group' => $group]);
    }

    public static function getGroup(string $group): array
    {
        return static::where('group', $group)->get()->pluck('value', 'key')->map(function ($val) {
            $decoded = json_decode($val, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $val;
        })->toArray();
    }
}
