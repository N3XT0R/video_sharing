<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

class VideoFactory extends Factory
{
    protected $model = Video::class;

    public function definition(): array
    {
        $ext = $this->faker->randomElement(['mp4', 'mov', 'avi', 'mkv']);
        $hash = $this->faker->sha256;

        return [
            'hash' => $hash,                                  // SHA-256 hex
            'ext' => $ext,
            'bytes' => $this->faker->numberBetween(100_000, 2_000_000_000), // ~100KB..~2GB
            'path' => "videos/{$hash}.{$ext}",
            'meta' => [
                'duration' => $this->faker->numberBetween(5, 1200),    // seconds
                'width' => $this->faker->randomElement([1280, 1920, 2560]),
                'height' => $this->faker->randomElement([720, 1080, 1440]),
                'codec' => $this->faker->randomElement(['h264', 'hevc', 'mpeg4']),
                'fps' => $this->faker->randomElement([24, 25, 30, 60]),
            ],
            'original_name' => $this->faker->unique()->slug().".{$ext}",
            'disk' => 'local',                                // default disk
            'preview_url' => null,                            // set via state if needed
        ];
    }

    public function withPreviewUrl(): static
    {
        return $this->state(fn() => [
            'preview_url' => $this->faker->url(),
        ]);
    }

    public function onDisk(string $disk): static
    {
        return $this->state(fn() => ['disk' => $disk]);
    }

    public function small(): static
    {
        return $this->state(fn() => ['bytes' => $this->faker->numberBetween(100_000, 5_000_000)]);
    }

    public function large(): static
    {
        return $this->state(fn() => ['bytes' => $this->faker->numberBetween(500_000_000, 2_000_000_000)]);
    }
}