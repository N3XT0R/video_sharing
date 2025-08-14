<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Config;
use App\Models\ConfigCategory;
use App\Services\Contracts\ConfigServiceInterface;
use Illuminate\Support\Facades\Cache;

class ConfigService implements ConfigServiceInterface
{
    public function get(string $key, ?string $category = null, mixed $default = null): mixed
    {
        [$cfgKey, $subKey] = explode('.', $key, 2) + [1 => null];

        $slug = $category ?? 'default';
        $cacheKey = $this->cacheKey($slug, $cfgKey, $subKey);

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        return rescue(function () use ($cfgKey, $subKey, $slug, $default) {
            $query = Config::query()
                ->with(['subSettings', 'category'])
                ->where('key', $cfgKey);

            if ($slug === 'default') {
                $query->where(function ($q) {
                    $q->whereNull('config_category_id')
                        ->orWhereHas('category', fn($q) => $q->where('key', 'default'));
                });
            } else {
                $query->whereHas('category', fn($q) => $q->where('key', $slug));
            }

            $config = $query->first();

            if (!$config) {
                return $default;
            }

            $subValues = $this->rememberConfig($config);

            return $subKey ? ($subValues[$subKey] ?? $default) : ($config->value ?? $default);
        }, $default);
    }

    public function has(string $key): bool
    {
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
        $slug = $category ?? 'default';
        $categoryModel = ConfigCategory::firstOrCreate(['key' => $slug]);

        $config = Config::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'cast_type' => $castType,
                'is_visible' => $isVisible,
                'config_category_id' => $categoryModel->id,
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

        Cache::forget("configs::map::{$config->key}");
        Cache::forget($this->cacheKey($slug, $config->key, null));

        $config->load('subSettings');
        foreach ($config->subSettings as $sub) {
            Cache::forget($this->cacheKey($slug, $config->key, $sub->key));
        }

        $config->load('category', 'subSettings');
        $this->rememberConfig($config);

        return $config;
    }

    /**
     * Cache the given config and its sub settings.
     *
     * @return array<string, mixed> cached sub-setting values
     */
    public function rememberConfig(Config $config): array
    {
        $slug = $config->category?->key ?? 'default';

        Cache::forever("configs::map::{$config->key}", $slug);
        Cache::forever($this->cacheKey($slug, $config->key, null), $config->value);

        $subValues = [];
        foreach ($config->subSettings as $sub) {
            Cache::forever($this->cacheKey($slug, $config->key, $sub->key), $sub->value);
            $subValues[$sub->key] = $sub->value;
        }

        return $subValues;
    }

    private function cacheKey(string $slug, string $configKey, ?string $subKey): string
    {
        $base = "configs::{$slug}::{$configKey}";
        return $subKey ? "{$base}::{$subKey}" : $base;
    }
}