<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Validator;

class Config extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'value', 'is_visible', 'cast_type'];

    protected $casts = [
        'is_visible' => 'bool',
    ];

    /**
     * Cast the value attribute according to the cast_type column.
     */
    protected function value(): Attribute
    {
        return Attribute::make(
            get: fn($value, array $attributes) => self::castValue($attributes['cast_type'] ?? 'string', $value),
            set: fn($value) => $value,
        );
    }

    protected static function booted(): void
    {
        static::saving(function (Config $config): void {
            $type = $config->cast_type ?? 'string';
            $raw = $config->attributes['value'] ?? null;

            Validator::make(
                ['value' => $raw],
                ['value' => self::validationRule($type)]
            )->validate();

            $config->attributes['value'] = self::prepareValue($type, $raw);
        });
    }

    protected static function castValue(string $type, mixed $value): mixed
    {
        return match ($type) {
            'integer', 'int' => (int) $value,
            'float', 'double', 'real' => (float) $value,
            'boolean', 'bool' => (bool) $value,
            'array', 'json' => json_decode((string) $value, true) ?? [],
            default => $value,
        };
    }

    protected static function prepareValue(string $type, mixed $value): mixed
    {
        return match ($type) {
            'array', 'json' => is_array($value) ? json_encode($value) : $value,
            'boolean', 'bool' => $value ? '1' : '0',
            default => (string) $value,
        };
    }

    protected static function validationRule(string $type): string
    {
        return match ($type) {
            'integer', 'int' => 'integer',
            'float', 'double', 'real' => 'numeric',
            'boolean', 'bool' => 'boolean',
            'array', 'json' => 'array',
            default => 'string',
        };
    }
}
