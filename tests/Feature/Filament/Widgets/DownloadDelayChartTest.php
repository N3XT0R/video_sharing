<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Widgets;

use App\Filament\Widgets\DownloadDelayChart;
use App\Models\Assignment;
use App\Models\Download;
use App\Models\Batch;
use Carbon\Carbon;
use Tests\DatabaseTestCase;

final class DownloadDelayChartTest extends DatabaseTestCase
{
    public function testCalculatesAverageAndMedianDelay(): void
    {
        $notified = Carbon::now();
        $batch = Batch::factory()->type('assign')->create();
        $a1 = Assignment::factory()->withBatch($batch)->create(['last_notified_at' => $notified]);
        $a2 = Assignment::factory()->withBatch($batch)->create(['last_notified_at' => $notified]);

        Download::factory()->forAssignment($a1)->create([
            'downloaded_at' => $notified->copy()->addMinutes(10),
        ]);
        Download::factory()->forAssignment($a2)->create([
            'downloaded_at' => $notified->copy()->addMinutes(20),
        ]);

        $widget = app(DownloadDelayChart::class);
        $widget->filters = [
            'from' => now()->subDay()->toDateString(),
            'to' => now()->addDay()->toDateString(),
        ];
        $data = \invade($widget)->getData();

        $this->assertSame(['Average', 'Median'], $data['labels']);
        $this->assertEquals([15.0, 15.0], $data['datasets'][0]['data']);
    }
}
