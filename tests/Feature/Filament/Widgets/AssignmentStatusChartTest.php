<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Widgets;

use App\Enum\StatusEnum;
use App\Filament\Widgets\AssignmentStatusChart;
use App\Models\Assignment;
use App\Models\Channel;
use App\Models\Batch;
use Tests\DatabaseTestCase;

final class AssignmentStatusChartTest extends DatabaseTestCase
{
    public function testComputesPercentagesPerChannel(): void
    {
        $channelA = Channel::factory()->create(['name' => 'A']);
        $channelB = Channel::factory()->create(['name' => 'B']);
        $batch = Batch::factory()->type('assign')->create();

        Assignment::factory()->count(2)->forChannel($channelA)->withBatch($batch)->create([
            'status' => StatusEnum::PICKEDUP->value,
        ]);
        Assignment::factory()->forChannel($channelA)->withBatch($batch)->create([
            'status' => StatusEnum::NOTIFIED->value,
        ]);
        Assignment::factory()->forChannel($channelA)->withBatch($batch)->create([
            'status' => StatusEnum::REJECTED->value,
        ]);

        Assignment::factory()->forChannel($channelB)->withBatch($batch)->create([
            'status' => StatusEnum::PICKEDUP->value,
        ]);
        Assignment::factory()->forChannel($channelB)->withBatch($batch)->create([
            'status' => StatusEnum::NOTIFIED->value,
        ]);

        $widget = app(AssignmentStatusChart::class);
        $widget->filters = [
            'from' => now()->subDay()->toDateString(),
            'to' => now()->addDay()->toDateString(),
        ];
        $data = \invade($widget)->getData();

        $this->assertSame(['A', 'B'], $data['labels']);
        $this->assertEquals([
            [
                'label' => 'Picked up',
                'data' => [50.0, 50.0],
                'backgroundColor' => '#10b981',
                'borderColor' => '#10b981',
            ],
            [
                'label' => 'Notified',
                'data' => [25.0, 50.0],
                'backgroundColor' => '#3b82f6',
                'borderColor' => '#3b82f6',
            ],
            [
                'label' => 'Rejected',
                'data' => [25.0, 0.0],
                'backgroundColor' => '#ef4444',
                'borderColor' => '#ef4444',
            ],
        ], $data['datasets']);
    }
}
