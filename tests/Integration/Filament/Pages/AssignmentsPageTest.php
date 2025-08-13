<?php

declare(strict_types=1);

namespace Tests\Integration\Filament\Pages;

use App\Filament\Pages\Assignments;
use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Channel;
use Carbon\Carbon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\URL;
use Tests\DatabaseTestCase;

class AssignmentsPageTest extends DatabaseTestCase
{
    public function testOfferUrlColumnGeneratesSignedUrl(): void
    {
        Carbon::setTestNow('2024-01-01 00:00:00');

        $batch = Batch::factory()->type('assign')->create();
        $channel = Channel::factory()->create();
        $assignment = Assignment::factory()
            ->for($channel)
            ->withBatch($batch)
            ->create();

        $page = app(Assignments::class);
        $table = $page->table(Table::make($page));

        $column = $table->getColumn('offer_url');
        $column->record($assignment);

        $url = $column->getUrl();
        $expected = URL::temporarySignedRoute(
            'offer.show',
            Carbon::now()->addYears(10),
            ['batch' => $batch->id, 'channel' => $channel->id]
        );

        $this->assertSame($expected, $url);

        Carbon::setTestNow();
    }
}
