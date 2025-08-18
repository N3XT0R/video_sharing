<?php

declare(strict_types=1);

namespace App\Repository;

use App\Models\Config;
use App\Models\Config\Category;
use App\Repository\Contracts\ConfigRepositoryInterface;
use Illuminate\Database\DatabaseManager;

class EloquentConfigRepository implements ConfigRepositoryInterface
{
    public function __construct(private readonly DatabaseManager $db)
    {
    }

    public function findByKeyAndCategory(string $key, ?string $categorySlug): ?Config
    {
        // Normalize category: null means "default" semantics
        $slug = $categorySlug ?: 'default';

        $query = Config::query()
            ->with(['category'])
            ->where('key', $key);

        // "default" means: either no category assigned OR category with key 'default'
        if ($slug === 'default') {
            $query->where(function ($q) {
                $q->whereNull('config_category_id')
                    ->orWhereHas('category', fn($qq) => $qq->where('slug', 'default'));
            });
        } else {
            $query->whereHas('category', fn($q) => $q->where('slug', $slug));
        }

        return $query->first();
    }

    public function upsert(
        string $key,
        mixed $value,
        ?string $categorySlug,
        string $castType,
        bool $isVisible
    ): Config {
        // Ensure atomic write of config + sub-settings
        return $this->db->connection()->transaction(function () use (
            $key,
            $value,
            $categorySlug,
            $castType,
            $isVisible
        ) {
            $slug = $categorySlug ?: 'default';
            $category = Category::query()->firstOrCreate(['slug' => $slug]);

            /** @var Config $config */
            $config = Config::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'cast_type' => $castType,
                    'is_visible' => $isVisible,
                    'config_category_id' => $category->getKey(),
                ],
            );

            return $config->load('category');
        });
    }
}