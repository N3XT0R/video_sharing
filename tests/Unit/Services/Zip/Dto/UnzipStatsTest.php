<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Zip\Dto;

use App\Services\Zip\Dto\UnzipStats;
use PHPUnit\Framework\TestCase;

final class UnzipStatsTest extends TestCase
{
    /** Ensures default ctor yields empty lists and zero total. */
    public function testDefaultsAreEmpty(): void
    {
        $stats = new UnzipStats();

        $this->assertSame([], $stats->extractedArchives);
        $this->assertSame([], $stats->failedArchives);
        $this->assertSame([], $stats->skippedArchives);
        $this->assertSame(0, $stats->total());
    }

    /** Ensures arrays are normalized via array_values() (keys dropped, order preserved). */
    public function testNormalizesArrayKeysToValues(): void
    {
        $extracted = [5 => 'a.zip', 'foo' => 'b.zip'];
        $failed = [10 => 'x.zip'];
        $skipped = ['bar' => 'k.zip', 7 => 'm.zip'];

        $stats = new UnzipStats($extracted, $failed, $skipped);

        $this->assertSame(['a.zip', 'b.zip'], $stats->extractedArchives);
        $this->assertSame(['x.zip'], $stats->failedArchives);
        $this->assertSame(['k.zip', 'm.zip'], $stats->skippedArchives);
    }

    /** Verifies total equals the sum of all three category counts. */
    public function testTotalCountsAllItems(): void
    {
        $stats = new UnzipStats(
            ['a.zip', 'b.zip'],       // 2
            ['c.zip'],                // 1
            ['d.zip', 'e.zip', 'f.zip'] // 3
        );

        $this->assertSame(6, $stats->total());
    }

    /** Duplicates should be preserved (no de-duplication happens). */
    public function testDuplicatesArePreserved(): void
    {
        $stats = new UnzipStats(
            ['a.zip', 'a.zip'],
            ['b.zip', 'b.zip', 'b.zip'],
            []
        );

        $this->assertSame(['a.zip', 'a.zip'], $stats->extractedArchives);
        $this->assertSame(['b.zip', 'b.zip', 'b.zip'], $stats->failedArchives);
        $this->assertSame(5, $stats->total());
    }

    /** Sanity check: class is declared final as intended. */
    public function testClassIsFinal(): void
    {
        $ref = new \ReflectionClass(UnzipStats::class);
        $this->assertTrue($ref->isFinal(), 'UnzipStats should be final.');
    }
}
