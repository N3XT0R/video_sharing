<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Enum\TypeEnum;
use App\Models\Batch;
use App\Services\BatchService;
use RuntimeException;
use Tests\DatabaseTestCase;

class BatchServiceTest extends DatabaseTestCase
{
    public function testReturnsLatestFinishedAssignBatch(): void
    {
        // Arrange: some noise batches that must be ignored
        Batch::factory()->type('notify')->finished(['emails' => 1])->create(); // other type, finished
        Batch::factory()->type(TypeEnum::ASSIGN->value)->create();             // assign, NOT finished

        // Arrange: two finished assign batches; should return the one with the highest id
        $older = Batch::factory()
            ->type(TypeEnum::ASSIGN->value)
            ->finished(['expired' => 3])
            ->create();

        $newer = Batch::factory()
            ->type(TypeEnum::ASSIGN->value)
            ->finished(['expired' => 7])
            ->create();

        // Act
        $result = app(BatchService::class)->getLatestAssignBatch();

        // Assert: latest by id and finished
        $this->assertTrue($result->is($newer));
        $this->assertNotNull($result->finished_at);
        $this->assertSame(TypeEnum::ASSIGN->value, $result->type);
    }

    public function testThrowsWhenNoFinishedAssignBatchFound(): void
    {
        // Arrange: only non-finished or other types present
        Batch::factory()->type(TypeEnum::ASSIGN->value)->create();           // not finished
        Batch::factory()->type('notify')->finished(['emails' => 2])->create(); // finished but wrong type

        // Assert: service throws RuntimeException with expected message
        $this->withoutExceptionHandling();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Kein Assign-Batch gefunden.');

        // Act
        app(BatchService::class)->getLatestAssignBatch();
    }
}
