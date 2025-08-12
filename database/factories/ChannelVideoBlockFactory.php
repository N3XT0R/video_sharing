<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Channel;
use App\Models\ChannelVideoBlock;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChannelVideoBlockFactory extends Factory
{
    protected $model = ChannelVideoBlock::class;

    public function definition(): array
    {
        return [
            'channel_id' => Channel::factory(),
            'video_id' => Video::factory(),
            'until' => now()->addDays($this->faker->numberBetween(1, 14)),
        ];
    }

    public function forChannel(Channel $channel): static
    {
        return $this->state(fn() => ['channel_id' => $channel->getKey()]);
    }

    public function forVideo(Video $video): static
    {
        return $this->state(fn() => ['video_id' => $video->getKey()]);
    }

    public function until(\DateTimeInterface $when): static
    {
        return $this->state(fn() => ['until' => $when]);
    }

    public function expired(): static
    {
        return $this->state(fn() => ['until' => now()->subDay()]);
    }
}
