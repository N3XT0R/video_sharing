<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\Download;
use Illuminate\Database\Eloquent\Factories\Factory;

class DownloadFactory extends Factory
{
    protected $model = Download::class;

    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'downloaded_at' => now(),
            'ip' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'bytes_sent' => $this->faker->numberBetween(10_000, 5_000_000_000), // 10 KB .. 5 GB
        ];
    }

    public function forAssignment(Assignment $assignment): static
    {
        return $this->state(fn() => ['assignment_id' => $assignment->getKey()]);
    }

    public function at(\DateTimeInterface $when): static
    {
        return $this->state(fn() => ['downloaded_at' => $when]);
    }

    public function fromIp(string $ip): static
    {
        return $this->state(fn() => ['ip' => $ip]);
    }

    public function withUserAgent(string $ua): static
    {
        return $this->state(fn() => ['user_agent' => $ua]);
    }

    public function withBytesSent(int $bytes): static
    {
        return $this->state(fn() => ['bytes_sent' => $bytes]);
    }
}