<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface ConfigServiceInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function has(string $key): bool;
}