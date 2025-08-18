<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\Config;

interface ConfigServiceInterface
{
    public function get(
        string $key,
        ?string $category = null,
        mixed $default = null,
        bool $withoutCache = false
    ): mixed;

    public function set(
        string $key,
        mixed $value,
        ?string $category = null,
        string $castType = 'string',
        bool $isVisible = true
    ): Config;

    public function has(string $key, ?string $category = null): bool;
}