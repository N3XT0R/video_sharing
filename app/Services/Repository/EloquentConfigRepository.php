<?php

// app/Infrastructure/Config/EloquentConfigRepository.php
declare(strict_types=1);

namespace App\Services\Repository;

use App\Models\Config;
use App\Models\ConfigCategory;
use App\Services\Contracts\ConfigRepositoryInterface;
use Illuminate\Database\DatabaseManager;

final class EloquentConfigRepository implements ConfigRepositoryInterface
{
    public function __construct(private readonly DatabaseManager $db)
    {
    }

    public function findByKeyAndCategory(string $key, ?string $categorySlug): ?Config
    {
        $slug = $categorySlug ?: 'default';

        $query = Config::query()
            ->with(['subSettings', 'category'])
            ->where('key', $key);

        if ($slug === 'default') {
            $query->where(function ($q) {
                $q->whereNull('config_category_id')
                    ->orWhereHas('category', fn($qq) => $qq->where('key', 'default'));
            });
        } else {
            $query->whereHas('category', fn($q) => $q->where('key', $slug));
        }

        return $query->first();
    }

    public function upsert(
        string $key,
        mixed $value,
        ?string $categorySlug,
        array $subSettings,
        string $castType,
        bool $isVisible
    ): Config {
        return $this->db->connection()->transaction(function () use (
            $key,
            $value,
            $categorySlug,
            $subSettings,
            $castType,
            $isVisible
        ) {
            $slug = $categorySlug ?: 'default';
            $category = ConfigCategory::query()->firstOrCreate(['key' => $slug]);

            /** @var Config $config */
            $config = Config::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'cast_type' => $castType,
                    'is_visible' => $isVisible,
                    'config_category_id' => $category->id,
                ],
            );

            foreach ($subSettings as $subKey => $data) {
                $config->subSettings()->updateOrCreate(
                    ['key' => $subKey],
                    [
                        'value' => $data['value'] ?? null,
                        'cast_type' => $data['cast_type'] ?? 'string',
                    ],
                );
            }

            return $config->load('category', 'subSettings');
        });
    }
}
