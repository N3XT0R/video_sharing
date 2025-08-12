<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enum\StatusEnum;
use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Channel;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AssignmentFactory extends Factory
{
    protected $model = Assignment::class;

    public function definition(): array
    {
        return [
            'video_id' => Video::factory(),
            'channel_id' => Channel::factory(),
            'batch_id' => null, // or: Batch::factory()
            'status' => StatusEnum::QUEUED->value,
            'expires_at' => now()->addDays($this->faker->numberBetween(3, 14)),
            'attempts' => 0,
            'last_notified_at' => null,
            'download_token' => null, // set via state when needed
        ];
    }

    public function queued(): static
    {
        return $this->state(fn() => ['status' => StatusEnum::QUEUED->value]);
    }

    public function withBatch(Batch $batch = null): static
    {
        return $this->state(fn() => [
            'batch_id' => $batch?->getKey() ?? Batch::factory(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn() => ['expires_at' => now()->subDay()]);
    }

    public function withDownloadToken(?string $token = null): static
    {
        return $this->state(fn() => [
            'download_token' => $token ?? Str::random(40),
        ]);
    }

    public function forChannel(Channel $channel): static
    {
        return $this->state(fn() => ['channel_id' => $channel->getKey()]);
    }

    public function forVideo(Video $video): static
    {
        return $this->state(fn() => ['video_id' => $video->getKey()]);
    }
}