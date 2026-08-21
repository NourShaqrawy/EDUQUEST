<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    /**
     * قراءة قيمة إعداد مع قيمة افتراضية.
     */
    public static function get(string $key, $default = null)
    {
        return static::query()->whereKey($key)->value('value') ?? $default;
    }

    /**
     * كتابة/تحديث قيمة إعداد.
     */
    public static function put(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string) $value]);
    }
}
