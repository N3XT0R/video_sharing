<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Batch;
use Illuminate\Database\Eloquent\Factories\Factory;

class BatchFactory extends Factory
{
    protected $model = Batch::class;

    public function definition(): array
    {
        return [
            'type' => $this->faker->randomElement(['notify', 'assign', 'ingest', 'zip']),
            'started_at' => now(),
            'finished_at' => null,
            'stats' => [],
        ];
    }

    public function type(string $type): static
    {
        return $this->state(fn() => ['type' => $type]);
    }

    public function finished(array $stats = []): static
    {
        return $this->state(fn() => [
            'finished_at' => now(),
            'stats' => $stats ?: ['ok' => true],
        ]);
    }
}