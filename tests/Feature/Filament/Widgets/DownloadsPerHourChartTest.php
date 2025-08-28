<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Widgets;

use App\Filament\Widgets\DownloadsPerHourChart;
use App\Models\Assignment;
use App\Models\Download;
use App\Models\Batch;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\DatabaseTestCase;

final class DownloadsPerHourChartTest extends DatabaseTestCase
{
    public function testAggregatesDownloadsPerHour(): void
    {
        $pdo = DB::connection()->getPdo();
        if (method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('HOUR', fn($value) => (int) Carbon::parse($value)->format('H'));
        }

        $assignment = Assignment::factory()->withBatch(Batch::factory()->type('assign')->create())->create();
        $base = Carbon::now()->startOfDay();
        Download::factory()->forAssignment($assignment)->at($base->copy()->setHour(14))->create();
        Download::factory()->forAssignment($assignment)->at($base->copy()->setHour(16))->create();
        Download::factory()->forAssignment($assignment)->at($base->copy()->setHour(16)->addMinutes(30))->create();

        $widget = app(DownloadsPerHourChart::class);
        $widget->filters = [
            'from' => now()->subDay()->toDateString(),
            'to' => now()->addDay()->toDateString(),
        ];
        $data = \invade($widget)->getData();

        $this->assertSame('Downloads', $data['datasets'][0]['label']);
        $this->assertSame(1, $data['datasets'][0]['data'][14]);
        $this->assertSame(2, $data['datasets'][0]['data'][16]);
        $this->assertSame('14', $data['labels'][14]);
        $this->assertSame('16', $data['labels'][16]);
    }
}
