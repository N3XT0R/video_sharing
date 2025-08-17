<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\Config;

interface ConfigRepositoryInterface
{
    public function findByKeyAndCategory(string $key, ?string $categorySlug): ?Config;

    /**
     * @param  array<string,array{value:mixed,cast_type?:string}>  $subSettings
     */
    public function upsert(
        string $key,
        mixed $value,
        ?string $categorySlug,
        array $subSettings,
        string $castType,
        bool $isVisible
    ): Config;
}