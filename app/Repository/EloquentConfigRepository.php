<?php

declare(strict_types=1);

namespace App\Repository;

use App\Models\Config;
use App\Models\Config\Category;
use App\Repository\Contracts\ConfigRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;

class EloquentConfigRepository implements ConfigRepositoryInterface
{
    public function __construct(private readonly DatabaseManager $db)
    {
    }

    public function findByKeyAndCategory(string $key, ?string $categorySlug): ?Config
    {
        return $this->queryForKeyAndCategory($key, $categorySlug, withRelations: true)->first();
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

    public function existsByKeyAndCategory(string $key, ?string $categorySlug): bool
    {
        return $this->queryForKeyAndCategory($key, $categorySlug)->exists();
    }

    /**
     * Build the base query for a given config key and category semantics.
     *
     * - If $categorySlug is null or 'default', treat "default" as:
     *   (a) category is NULL OR (b) category.slug = 'default'.
     * - Otherwise require category.slug = $categorySlug.
     *
     * @param  string  $key
     * @param  string|null  $categorySlug
     * @param  bool  $withRelations  When true, eager-loads 'category'.
     * @return Builder<Config>
     */
    private function queryForKeyAndCategory(
        string $key,
        ?string $categorySlug,
        bool $withRelations = false
    ): Builder {
        $slug = $categorySlug ?: 'default';

        $query = Config::query()->where('key', $key);

        if ($withRelations) {
            $query->with('category');
        }

        if ($slug === 'default') {
            // Default semantics: either no category set OR the 'default' category
            $query->where(function (Builder $q) {
                $q->whereNull('config_category_id')
                    ->orWhereHas('category', fn(Builder $qq) => $qq->where('slug', 'default'));
            });
        } else {
            // Explicit category
            $query->whereHas('category', fn(Builder $q) => $q->where('slug', $slug));
        }

        return $query;
    }
}