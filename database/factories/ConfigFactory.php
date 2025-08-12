<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Config;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ConfigFactory extends Factory
{
    protected $model = Config::class;

    public function definition(): array
    {
        // Common-ish keys; adjust to your needs
        $key = $this->faker->unique()->randomElement([
            'site.name',
            'site.locale',
            'dropbox_refresh_token',
            'ui.theme',
            'feature.flags',
        ]);

        return [
            'key' => $key,
            'value' => match ($key) {
                'site.name' => $this->faker->company(),
                'site.locale' => $this->faker->randomElement(['de', 'en']),
                'dropbox_refresh_token' => Str::random(64),
                'ui.theme' => $this->faker->randomElement(['light', 'dark']),
                'feature.flags' => json_encode(['realtimeZip' => true]),
                default => $this->faker->sentence(),
            },
        ];
    }

    public function withKey(string $key): static
    {
        return $this->state(fn() => ['key' => $key]);
    }

    public function withValue(string $value): static
    {
        return $this->state(fn() => ['value' => $value]);
    }

    public function json(array $data): static
    {
        return $this->state(fn() => ['value' => json_encode($data)]);
    }

    public function dropboxRefreshToken(?string $token = null): static
    {
        return $this->state(fn() => [
            'key' => 'dropbox_refresh_token',
            'value' => $token ?? Str::random(64),
        ]);
    }
}