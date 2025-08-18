<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Config\Category;
use App\Support\ConfigCaster;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;

class Config extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'value', 'is_visible', 'cast_type', 'config_category_id'];

    protected $casts = [
        'is_visible' => 'bool',
    ];

    /**
     * Cast the "value" attribute according to the "cast_type" column.
     * Read: DB -> PHP (via ConfigCaster::toPhp)
     * Write: pass-through (final normalization happens in saving hook)
     */
    protected function value(): Attribute
    {
        return Attribute::make(
            get: fn($value, array $attributes) => ConfigCaster::toPhp($attributes['cast_type'] ?? 'string', $value),

            // keep write-through; we normalize & validate on saving
            set: fn($value) => $value,
        );
    }

    /**
     * Validate and normalize "value" before persisting.
     * - Validation rule depends on cast_type (int/float/bool/json/string)
     * - Storage representation is normalized via ConfigCaster::toStorage
     */
    protected static function booted(): void
    {
        static::saving(function (Config $config): void {
            // Determine type and read the raw value (avoid accessor recursion)
            $type = $config->cast_type ?? 'string';
            $raw = $config->attributes['value'] ?? null;

            // Validate raw input against dynamic rule
            Validator::make(
                ['value' => $raw],
                ['value' => ConfigCaster::rule($type, $raw)]
            )->validate();

            // Normalize to storage representation (strings for scalars, JSON string for arrays)
            $config->attributes['value'] = ConfigCaster::toStorage($type, $raw);
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'config_category_id');
    }
}
