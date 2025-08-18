<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\TypeEnum;
use App\Models\Batch;
use RuntimeException;

class BatchService
{
    public function getLatestAssignBatch(): Batch
    {
        $assignBatch = Batch::query()
            ->where('type', TypeEnum::ASSIGN->value)
            ->whereNotNull('finished_at')
            ->latest('id')
            ->first();
        if (false === $assignBatch instanceof Batch) {
            throw new RuntimeException('Kein Assign-Batch gefunden.');
        }

        return $assignBatch;
    }

    public function getAssignBatchById(int $id): Batch
    {
        $assignBatch = Batch::query()
            ->where('type', TypeEnum::ASSIGN->value)
            ->whereNotNull('finished_at')
            ->whereKey($id)
            ->first();

        if (false === $assignBatch instanceof Batch) {
            throw new RuntimeException('Kein Assign-Batch gefunden.');
        }

        return $assignBatch;
    }

    public function resetFinishDate(Batch $batch): bool
    {
        if ($batch->getAttribute('type') !== TypeEnum::class->value) {
            throw new RuntimeException('Kein Assign-Batch.');
        }
    }
}