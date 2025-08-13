<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Config;
use App\Services\Contracts\ConfigServiceInterface;

class ConfigService implements ConfigServiceInterface
{
    public function get(string $key, mixed $default = null): mixed
    {
        return rescue(
            fn() => Config::query()->where('key', $key)->first()?->value ?? $default,
            $default
        );
    }

    public function has(string $key): bool
    {
        return Config::query()->where('key', $key)->exists();
    }


}