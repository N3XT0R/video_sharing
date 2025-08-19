<?php

declare(strict_types=1);

namespace App\Services\Zip\Dto;

/**
 * Immutable stats about an unzip run.
 */
final class UnzipStats
{
    /** @var list<string> */
    public array $extractedArchives;
    /** @var list<string> */
    public array $failedArchives;
    /** @var list<string> */
    public array $skippedArchives; // e.g., archives with no safe entries

    public function __construct(
        array $extractedArchives = [],
        array $failedArchives = [],
        array $skippedArchives = [],
    ) {
        $this->extractedArchives = array_values($extractedArchives);
        $this->failedArchives = array_values($failedArchives);
        $this->skippedArchives = array_values($skippedArchives);
    }

    public function total(): int
    {
        return \count($this->extractedArchives) + \count($this->failedArchives) + \count($this->skippedArchives);
    }
}