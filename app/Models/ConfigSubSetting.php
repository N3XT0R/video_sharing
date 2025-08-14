<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\ConfigCaster;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;

class ConfigSubSetting extends Model
{
    use HasFactory;

    protected $fillable = ['config_id', 'key', 'value', 'cast_type'];

    public function config(): BelongsTo
    {
        return $this->belongsTo(Config::class);
    }

    protected function value(): Attribute
    {
        return Attribute::make(
            get: fn($value, array $attributes) => ConfigCaster::toPhp($attributes['cast_type'] ?? 'string', $value),
            set: fn($value) => $value,
        );
    }

    protected static function booted(): void
    {
        static::saving(function (ConfigSubSetting $setting): void {
            $type = $setting->cast_type ?? 'string';
            $raw = $setting->attributes['value'] ?? null;
            Validator::make(
                ['value' => $raw],
                ['value' => ConfigCaster::rule($type, $raw)]
            )->validate();
            $setting->attributes['value'] = ConfigCaster::toStorage($type, $raw);
        });
    }
}
