<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Batch;
use Illuminate\Support\Carbon;
use Tests\DatabaseTestCase;

/**
 * Unit tests for the App\Models\Batch model.
 *
 * We validate:
 *  - mass assignment + attribute casts
 *  - updating "finished_at" and "stats"
 *  - optional factory states (type/finished) produce sane values
 */
final class BatchTest extends DatabaseTestCase
{
    public function testMassAssignmentAndCasts(): void
    {
        $started = '2025-08-10 11:22:33';

        $batch = Batch::query()->create([
            'type' => 'assign',
            'started_at' => $started,
            'finished_at' => null,
            'stats' => ['emails' => 0, 'disk' => 'local'],
        ])->fresh();

        // Type persisted
        $this->assertSame('assign', $batch->type);

        // Casts: started_at is Carbon; finished_at is null
        $this->assertInstanceOf(Carbon::class, $batch->started_at);
        $this->assertTrue($batch->started_at->equalTo(Carbon::parse($started)));
        $this->assertNull($batch->finished_at);

        // Stats cast to array
        $this->assertIsArray($batch->stats);
        $this->assertSame(['emails' => 0, 'disk' => 'local'], $batch->stats);
    }

    public function testUpdateFinishedAtAndStatsPersists(): void
    {
        $batch = Batch::factory()->create([
            'type' => 'assign',
            'started_at' => '2025-08-11 08:00:00',
            'finished_at' => null,
            'stats' => [],
        ]);

        $finished = '2025-08-11 08:05:00';
        $batch->update([
            'finished_at' => $finished,
            'stats' => ['expired' => 3],
        ]);
        $batch = $batch->fresh();

        $this->assertInstanceOf(Carbon::class, $batch->finished_at);
        $this->assertTrue($batch->finished_at->equalTo(Carbon::parse($finished)));
        $this->assertSame(['expired' => 3], $batch->stats);
    }

    public function testFactoryStatesProduceValidValues(): void
    {
        // If your factory defines state helpers like ->type('assign') and ->finished()
        $batch = Batch::factory()->type('assign')->finished()->create();

        $this->assertSame('assign', $batch->type);
        $this->assertInstanceOf(Carbon::class, $batch->started_at);
        $this->assertInstanceOf(Carbon::class, $batch->finished_at);
        $this->assertNotNull($batch->started_at);
        $this->assertNotNull($batch->finished_at);
    }
}
