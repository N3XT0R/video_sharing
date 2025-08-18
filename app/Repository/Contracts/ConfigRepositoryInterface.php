<?php

declare(strict_types=1);

namespace App\Repository\Contracts;

use App\Models\Config;

interface ConfigRepositoryInterface
{

    public function findByKeyAndCategory(string $key, ?string $categorySlug): ?Config;

    public function upsert(
        string $key,
        mixed $value,
        ?string $categorySlug,
        string $castType,
        bool $isVisible
    ): Config;

    public function existsByKeyAndCategory(string $key, ?string $categorySlug): bool;

    public function deleteByKeyAndCategory(string $key, ?string $categorySlug): int;
}