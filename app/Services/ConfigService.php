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


    public function get(string $key, ?string $category = null, mixed $default = null, bool $withoutCache = false): mixed
    {
        $slug = $category ?: self::DEFAULT;

        // 1) Try cache first (sub-key prioritized)
        if (false === $withoutCache) {
            $cacheKey = $this->cacheKey($slug, $key);
            if ($this->cache->has($cacheKey)) {
                return $this->cache->get($cacheKey);
            }
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
        if (false === $withoutCache) {
            $this->rememberConfig($config);
        }
        return $config->getAttribute('value') ?? $default;
    }

    public function has(string $key, ?string $category = null): bool
    {
        $slug = $category ?: 'default';

        // Cache-first: explicit existence flag
        try {
            if ($this->cache->get($this->existsKey($slug, $key), false) === true) {
                return true;
            }

            // If the concrete value for this slug/key is cached, we also know it exists
            $sentinel = new \stdClass();
            $cached = $this->cache->get($this->cacheKey($slug, $key), $sentinel);
            if ($cached !== $sentinel) {
                return true;
            }
        } catch (\Throwable) {
            // Ignore cache errors; we'll fall back to DB.
        }

        // DB fallback via repository
        try {
            $exists = $this->repo->existsByKeyAndCategory($key, $slug);
            if ($exists) {
                // Memoize for future calls
                $this->cache->forever($this->existsKey($slug, $key), true);
            }
            return $exists;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Build an existence cache key for {category}/{key}. */
    private function existsKey(string $slug, string $configKey): string
    {
        return "configs::exists::{$slug}::{$configKey}";
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
     * Delete a config by key and optional category.
     * Returns true if at least one row was deleted.
     */
    public function delete(string $key, ?string $category = null): bool
    {
        $slug = $category ?: self::DEFAULT;

        // Try to load once for precise cache invalidation (safe guarded)
        $config = null;
        try {
            $config = $this->repo->findByKeyAndCategory($key, $slug);
        } catch (\Throwable) {
            // ignore; we'll still attempt deletion and cache cleanup using map
        }

        // Invalidate caches optimistically (before delete to avoid race)
        if ($config) {
            $this->forgetConfig($config);
        } else {
            $this->forgetByKeyWithSlugGuess($key, $slug);
        }

        // Delete in repository
        try {
            $affected = $this->repo->deleteByKeyAndCategory($key, $slug);
        } catch (\Throwable) {
            return false;
        }

        // Ensure reverse map is gone after deletion
        $this->cache->forget($this->mapKey($key));

        return $affected > 0;
    }


    /**
     * Forget caches for a key using either the hinted slug or the mapped last-known slug.
     * This is used when we don't have a hydrated model instance.
     */
    private function forgetByKeyWithSlugGuess(string $key, string $hintSlug): void
    {
        try {
            $mapped = $this->cache->get($this->mapKey($key));
            $slugs = array_values(array_unique(array_filter([$hintSlug, is_string($mapped) ? $mapped : null])));
            if ($slugs === []) {
                $slugs = [$hintSlug]; // fall back to hint
            }

            foreach ($slugs as $slug) {
                $this->cache->forget($this->cacheKey($slug, $key));
            }

            $this->cache->forget($this->mapKey($key));
        } catch (\Throwable) {
            // ignore cache errors
        }
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

        // Existence memo for cache-first has()
        $this->cache->forever($this->existsKey($slug, $key), true);

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
            $this->cache->forget($this->cacheKey($slug, $configKey));
            $this->cache->forget($this->existsKey($slug, $configKey));
        }

        // Finally remove the mapping itself
        $this->cache->forget($this->mapKey($configKey));
    }
}