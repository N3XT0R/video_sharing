<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Config;
use App\Services\Contracts\ConfigRepositoryInterface;
use App\Services\Contracts\ConfigServiceInterface;
use Illuminate\Contracts\Cache\Repository as Cache;
use Throwable;

final class ConfigService implements ConfigServiceInterface
{
    public function __construct(
        private readonly Cache $cache,
        private readonly ConfigRepositoryInterface $repo
    ) {
    }

    public function get(string $key, ?string $category = null, mixed $default = null): mixed
    {
        [$mainKey, $subKey] = $this->splitKey($key);
        $slug = $category ?: 'default';

        // 1) Try cache first (sub-key prioritized)
        $cacheKey = $this->cacheKey($slug, $mainKey, $subKey);
        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        // 2) DB fallback (wrapped to avoid leaking infra exceptions)
        try {
            $config = $this->repo->findByKeyAndCategory($mainKey, $slug);
        } catch (Throwable) {
            return $default;
        }

        if (!$config) {
            return $default;
        }

        // 3) Warm cache and return value
        $this->rememberConfig($config);

        if ($subKey !== null) {
            foreach ($config->subSettings as $sub) {
                if ($sub->key === $subKey) {
                    return $sub->value ?? $default;
                }
            }
            return $default;
        }

        return $config->value ?? $default;
    }

    public function has(string $key): bool
    {
        // Contract requires only $key; category inference stays with default semantics
        return $this->get($key) !== null;
    }

    public function set(
        string $key,
        mixed $value,
        ?string $category = null,
        array $subSettings = [],
        string $castType = 'string',
        bool $isVisible = true,
    ): Config {
        // Persist to DB (atomic)
        $config = $this->repo->upsert(
            key: $key,
            value: $value,
            categorySlug: $category,
            subSettings: $subSettings,
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
     * Remember a config and its sub-settings in the cache and return sub values.
     *
     * @return array<string, mixed>
     */
    public function rememberConfig(Config $config): array
    {
        $slug = $config->category?->key ?? 'default';

        // Store a reverse map for invalidation across category changes
        $this->cache->forever($this->mapKey($config->key), $slug);

        // Root value
        $this->cache->forever($this->cacheKey($slug, $config->key, null), $config->value);

        // Sub-values
        $subs = [];
        foreach ($config->subSettings as $sub) {
            $this->cache->forever($this->cacheKey($slug, $config->key, $sub->key), $sub->value);
            $subs[$sub->key] = $sub->value;
        }

        return $subs;
    }

    // -----------------------
    // Internal helper methods
    // -----------------------

    /**
     * Split "foo.bar" into ["foo", "bar"] or ["foo", null].
     *
     * @return array{0:string,1:?string}
     */
    private function splitKey(string $key): array
    {
        return explode('.', $key, 2) + [1 => null];
    }

    /**
     * Build a cache key for {category}/{key}/{sub?}.
     */
    private function cacheKey(string $slug, string $configKey, ?string $subKey): string
    {
        $base = "configs::{$slug}::{$configKey}";
        return $subKey ? "{$base}::{$subKey}" : $base;
    }

    /**
     * Build a mapping key to remember the last-known category of a config key.
     */
    private function mapKey(string $configKey): string
    {
        return "configs::map::{$configKey}";
    }

    /**
     * Forget cached entries for both the current and last-known category.
     * This prevents stale cache when a config is moved between categories.
     */
    private function forgetConfig(Config $config): void
    {
        $newSlug = $config->category?->key ?? 'default';
        $oldSlug = $this->cache->get($this->mapKey($config->key), $newSlug);

        foreach (array_unique([$newSlug, $oldSlug]) as $slug) {
            // Root
            $this->cache->forget($this->cacheKey($slug, $config->key, null));
            // Subs
            $subSettings = $config->getAttribute('subSettings');
            foreach ($subSettings as $sub) {
                $this->cache->forget($this->cacheKey($slug, $config->key, $sub->key));
            }
        }

        // Finally remove the mapping itself
        $this->cache->forget($this->mapKey($config->key));
    }
}
