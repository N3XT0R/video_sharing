<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Channel;
use App\Models\Clip;
use App\Models\Video;
use App\Services\CsvService;
use Tests\DatabaseTestCase;

class CsvServiceTest extends DatabaseTestCase
{
    public function testBuildInfoCsvGeneratesHeaderAndRowsForVideosWithAndWithoutClips(): void
    {
        // Arrange: shared finished assign batch + single channel (respecting unique (video, channel))
        $batch = Batch::factory()->type('assign')->finished()->create();
        $channel = Channel::factory()->create();

        // Video A: no clips, original_name fallback to basename(path), 10 MB (10.0)
        $videoA = Video::factory()->create([
            'hash' => 'hashA',
            'bytes' => 10 * 1024 * 1024,     // 10 MB
            'ext' => 'mp4',
            'path' => 'videos/clipA.mp4',
            'original_name' => null,
        ]);
        $assignmentA = Assignment::factory()
            ->for($batch, 'batch')->for($channel, 'channel')->for($videoA, 'video')
            ->create();

        // Video B: two clips, explicit original_name, 2 MB (2.0)
        $videoB = Video::factory()->create([
            'hash' => 'hashB',
            'bytes' => 2 * 1024 * 1024,      // 2 MB
            'ext' => 'mov',
            'path' => 'videos/clipB.mov',
            'original_name' => 'dashcam_B.mov',
        ]);
        // Two clips with times and metadata (mm:ss expected)
        Clip::factory()->forVideo($videoB)->range(5, 65)->state([
            'note' => 'first clip',
            'bundle_key' => 'bundle-123',
            'role' => 'editor',
            'submitted_by' => 'user@example.test',
        ])->create();

        Clip::factory()->forVideo($videoB)->range(75, 130)->state([
            'note' => 'second clip',
            'bundle_key' => 'bundle-456',
            'role' => 'review',
            'submitted_by' => 'user2@example.test',
        ])->create();

        $assignmentB = Assignment::factory()
            ->for($batch, 'batch')->for($channel, 'channel')->for($videoB, 'video')
            ->create();

        $items = collect([$assignmentA, $assignmentB]);

        // Act
        $csv = app(CsvService::class)->buildInfoCsv($items);

        // Assert: BOM is present (UTF-8 with BOM)
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        // Strip BOM for parsing and split into lines
        $clean = ltrim($csv, "\xEF\xBB\xBF");
        $lines = array_values(array_filter(array_map(static fn($l) => rtrim($l, "\r\n"), explode("\n", $clean)),
            fn($l) => $l !== ''));

        // Expect: header + 1 row for Video A + 2 rows for Video B = 4 lines total
        $this->assertCount(4, $lines);

        // Helper to parse a CSV line using semicolon delimiter
        $parse = static fn(string $line): array => str_getcsv($line, ';');

        // Header row
        $header = $parse($lines[0]);
        $this->assertSame(
            ['filename', 'hash', 'size_mb', 'start', 'end', 'note', 'bundle', 'role', 'submitted_by'],
            $header
        );

        // Row for Video A (no clips): start/end/note/bundle/role/submitted_by should be empty
        $rowA = $parse($lines[1]);
        $this->assertSame([
            'clipA.mp4',     // basename(path) because original_name is null
            'hashA',
            '10.0',
            '',              // start
            '',              // end
            '',              // note
            '',              // bundle
            '',              // role
            '',              // submitted_by
        ], $rowA);

        // Rows for Video B (two clips)
        $rowB1 = $parse($lines[2]);
        $rowB2 = $parse($lines[3]);

        // First clip: 5..65s -> "00:05" .. "01:05"
        $this->assertSame([
            'dashcam_B.mov',
            'hashB',
            '2.0',
            '00:05',
            '01:05',
            'first clip',
            'bundle-123',
            'editor',
            'user@example.test',
        ], $rowB1);

        // Second clip: 75..130s -> "01:15" .. "02:10"
        $this->assertSame([
            'dashcam_B.mov',
            'hashB',
            '2.0',
            '01:15',
            '02:10',
            'second clip',
            'bundle-456',
            'review',
            'user2@example.test',
        ], $rowB2);
    }
}
