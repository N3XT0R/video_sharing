<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\Config;

interface ConfigServiceInterface
{
    public function get(string $key, ?string $category = null, mixed $default = null): mixed;

    public function has(string $key): bool;

    public function set(
        string $key,
        mixed $value,
        ?string $category = null,
        array $subSettings = [],
        string $castType = 'string',
        bool $isVisible = true,
    ): Config;

    /**
     * Remember a config and its sub-settings in the cache and return sub values.
     *
     * @return array<string, mixed>
     */
    public function rememberConfig(Config $config): array;
}