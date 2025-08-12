<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Clip;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ClipFactory extends Factory
{
    protected $model = Clip::class;

    public function definition(): array
    {
        // Ensure end_sec > start_sec
        $start = $this->faker->numberBetween(0, 30 * 60);        // up to 30 min
        $len = $this->faker->numberBetween(2, 5 * 60);         // 2s .. 5 min
        $end = $start + $len;

        return [
            'video_id' => Video::factory(),
            'start_sec' => $start,
            'end_sec' => $end,
            'note' => $this->faker->optional()->sentence(),
            'bundle_key' => null,                                // set via state if needed
            'role' => null,                                // set via state if needed
            'submitted_by' => $this->faker->optional()->safeEmail(),
        ];
    }

    public function forVideo(Video $video): static
    {
        return $this->state(fn() => ['video_id' => $video->getKey()]);
    }

    public function range(int $startSec, int $endSec): static
    {
        if ($endSec <= $startSec) {
            throw new \InvalidArgumentException('endSec must be greater than startSec');
        }
        return $this->state(fn() => [
            'start_sec' => $startSec,
            'end_sec' => $endSec,
        ]);
    }

    public function short(int $seconds = 10): static
    {
        return $this->state(function () use ($seconds) {
            $start = $this->faker->numberBetween(0, 30 * 60 - max(2, $seconds));
            return [
                'start_sec' => $start,
                'end_sec' => $start + max(2, $seconds),
            ];
        });
    }

    public function withBundleKey(?string $key = null): static
    {
        return $this->state(fn() => ['bundle_key' => $key ?? Str::uuid()->toString()]);
    }

    public function role(string $role): static
    {
        return $this->state(fn() => ['role' => $role]);
    }

    public function submittedBy(string $who): static
    {
        return $this->state(fn() => ['submitted_by' => $who]);
    }
}