<?php

declare(strict_types=1);

namespace App\Support;

use App\Enum\ConfigTypeEnum;

class ConfigCaster
{
    /** @var array<string, ConfigTypeEnum> */
    private const array ALIASES = [
        // string
        'string' => ConfigTypeEnum::STRING,
        'str' => ConfigTypeEnum::STRING,
        'text' => ConfigTypeEnum::STRING,

        // int
        'int' => ConfigTypeEnum::INT,
        'integer' => ConfigTypeEnum::INT,

        // float
        'float' => ConfigTypeEnum::FLOAT,
        'double' => ConfigTypeEnum::FLOAT,
        'real' => ConfigTypeEnum::FLOAT,
        'number' => ConfigTypeEnum::FLOAT, // optional alias

        // bool
        'bool' => ConfigTypeEnum::BOOL,
        'boolean' => ConfigTypeEnum::BOOL,

        // json/array
        'json' => ConfigTypeEnum::JSON,
        'array' => ConfigTypeEnum::JSON,
    ];

    /**
     * Normalize a freeform type into a canonical ConfigType.
     */
    public static function normalize(?string $type): ConfigTypeEnum
    {
        $key = strtolower((string)$type);

        return self::ALIASES[$key] ?? ConfigTypeEnum::STRING;
    }

    /**
     * Convert a stored DB value (string) to a PHP value for reading.
     */
    public static function toPhp(?string $type, mixed $value): mixed
    {
        return match (self::normalize($type)) {
            ConfigTypeEnum::INT => (int)$value,
            ConfigTypeEnum::FLOAT => (float)$value,
            ConfigTypeEnum::BOOL => self::toBool($value),
            ConfigTypeEnum::JSON => self::decodeJsonArray($value),
            ConfigTypeEnum::STRING => $value,
        };
    }

    /**
     * Convert an incoming PHP value to a storage representation for DB write.
     * (Typically strings for scalar columns; JSON as string.)
     */
    public static function toStorage(?string $type, mixed $value): mixed
    {
        return match (self::normalize($type)) {
            ConfigTypeEnum::INT => is_numeric($value) ? (string)(int)$value : (string)$value,
            ConfigTypeEnum::FLOAT => is_numeric($value) ? (string)(float)$value : (string)$value,
            ConfigTypeEnum::BOOL => self::toBool($value) ? '1' : '0',
            ConfigTypeEnum::JSON => is_array($value) ? json_encode($value) : (string)$value,
            ConfigTypeEnum::STRING => (string)$value,
        };
    }

    /**
     * Return a Laravel validation rule for the given type.
     * Optionally pass raw input to choose 'json' vs 'array' dynamically.
     */
    public static function rule(?string $type, mixed $raw = null): string
    {
        return match (self::normalize($type)) {
            ConfigTypeEnum::INT => 'integer',
            ConfigTypeEnum::FLOAT => 'numeric',
            ConfigTypeEnum::BOOL => 'boolean',
            ConfigTypeEnum::JSON => is_string($raw) ? 'json' : 'array',
            ConfigTypeEnum::STRING => 'string',
        };
    }

    // --- helpers -------------------------------------------------------------

    /**
     * Robust boolean normalization for strings like "true/false", "on/off", "yes/no", "1/0".
     */
    private static function toBool(mixed $v): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        if (is_int($v)) {
            return $v === 1;
        }
        if (is_string($v)) {
            return filter_var($v, FILTER_VALIDATE_BOOLEAN);
        }
        return (bool)$v;
    }

    /**
     * Decode JSON column to PHP array; return [] on failure.
     */
    private static function decodeJsonArray(mixed $v): array
    {
        if (is_array($v)) {
            return $v;
        }
        $decoded = json_decode((string)$v, true);
        return is_array($decoded) ? $decoded : [];
    }
}