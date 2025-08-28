<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Widgets;

use App\Filament\Widgets\UploadStatsChart;
use App\Models\Clip;
use Tests\DatabaseTestCase;

final class UploadStatsChartTest extends DatabaseTestCase
{
    public function testCountsUploadsPerSubmitter(): void
    {
        Clip::factory()->count(2)->submittedBy('alice@example.com')->create();
        Clip::factory()->submittedBy('bob@example.com')->create();

        $widget = app(UploadStatsChart::class);
        $widget->filters = [
            'from' => now()->subDay()->toDateString(),
            'to' => now()->addDay()->toDateString(),
        ];
        $data = \invade($widget)->getData();

        $this->assertSame(['alice@example.com', 'bob@example.com'], $data['labels']);
        $this->assertSame([
            [
                'label' => 'Uploads',
                'data' => [2, 1],
            ],
        ], $data['datasets']);
    }
}
