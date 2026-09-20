<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
    ];

    public static function get($key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        if (!$setting) {
            return $default;
        }

        if ($setting->type === 'json' || $setting->type === 'array') {
            return json_decode($setting->value, true);
        }
        if ($setting->type === 'boolean') {
            return filter_var($setting->value, FILTER_VALIDATE_BOOLEAN);
        }
        if ($setting->type === 'integer' || $setting->type === 'number') {
            return (int) $setting->value;
        }

        return $setting->value;
    }

    public static function set($key, $value, $group = 'general', $type = 'string')
    {
        $stringValue = is_array($value) ? json_encode($value) : (string) $value;
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $stringValue, 'group' => $group, 'type' => $type]
        );
    }
}
