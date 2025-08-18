<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Config;
use App\Repository\Contracts\ConfigRepositoryInterface;
use App\Services\Contracts\ConfigServiceInterface;
use Illuminate\Contracts\Cache\Repository as Cache;
use Throwable;

readonly class ConfigService implements ConfigServiceInterface
{

    private const string DEFAULT = 'default';

    public function __construct(
        private Cache $cache,
        private ConfigRepositoryInterface $repo
    ) {
    }


    public function get(string $key, ?string $category = null, mixed $default = null): mixed
    {
        $slug = $category ?: self::DEFAULT;

        // 1) Try cache first (sub-key prioritized)
        $cacheKey = $this->cacheKey($slug, $key);
        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        // 2) DB fallback (wrapped to avoid leaking infra exceptions)
        try {
            $config = $this->repo->findByKeyAndCategory($key, $slug);
        } catch (Throwable) {
            return $default;
        }

        if (!$config) {
            return $default;
        }


        // 3) Warm cache and return value
        $this->rememberConfig($config);
        return $config->getAttribute('value') ?? $default;
    }

    public function has(string $key): bool
    {
        return Config::query()->where('key', $key)->exists();
    }


    public function set(
        string $key,
        mixed $value,
        ?string $category = null,
        string $castType = 'string',
        bool $isVisible = true
    ): Config {
        // Persist to DB (atomic)
        $config = $this->repo->upsert(
            key: $key,
            value: $value,
            categorySlug: $category,
            castType: $castType,
            isVisible: $isVisible
        );

        // Invalidate old cache entries (handles possible category change)
        $this->forgetConfig($config);

        // Warm cache with fresh values
        $this->rememberConfig($config);

        return $config;
    }


    /**
     * Build a cache key for {category}/{key}.
     */
    private function cacheKey(string $slug, string $configKey): string
    {
        return "configs::{$slug}::{$configKey}";
    }

    /**
     * Build a mapping key to remember the last-known category of a config key.
     */
    private function mapKey(string $configKey): string
    {
        return "configs::map::{$configKey}";
    }

    /**
     * Remember a config and its sub-settings in the cache and return sub values.
     *
     */
    public function rememberConfig(Config $config): void
    {
        $key = $config->getAttribute('key');
        $value = $config->getAttribute('value');
        $slug = $config->getAttribute('category')?->getAttribute('slug') ?? self::DEFAULT;

        // Store a reverse map for invalidation across category changes
        $this->cache->forever($this->mapKey($key), $slug);

        // Root value
        $this->cache->forever($this->cacheKey($slug, $key), $value);
    }


    /**
     * Forget cached entries for both the current and last-known category.
     * This prevents stale cache when a config is moved between categories.
     */
    private function forgetConfig(Config $config): void
    {
        $configKey = $config->getAttribute('key');
        $newSlug = $config->category?->getAttribute('slug') ?? self::DEFAULT;
        $oldSlug = $this->cache->get($this->mapKey($configKey), $newSlug);

        foreach (array_unique([$newSlug, $oldSlug]) as $slug) {
            // Root
            $this->cache->forget($this->cacheKey($slug, $configKey));
        }

        // Finally remove the mapping itself
        $this->cache->forget($this->mapKey($configKey));
    }
}