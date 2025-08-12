<?php

declare(strict_types=1);

namespace Tests\Integration\Filament;

use App\Filament\Resources\AssignmentResource;
use App\Filament\Resources\AssignmentResource\Pages\ListAssignments;
use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Channel;
use Carbon\Carbon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\URL;
use Tests\DatabaseTestCase;

class AssignmentResourceTest extends DatabaseTestCase
{
    public function test_offer_action_generates_signed_url(): void
    {
        Carbon::setTestNow('2024-01-01 00:00:00');

        $batch = Batch::factory()->type('assign')->create();
        $channel = Channel::factory()->create();
        $assignment = Assignment::factory()
            ->for($channel)
            ->withBatch($batch)
            ->create();

        $page = app(ListAssignments::class);
        $table = AssignmentResource::table(Table::make($page));

        $action = $table->getFlatActions()['offer'];
        $action->record($assignment);

        $url = $action->getUrl();
        $expected = URL::temporarySignedRoute(
            'offer.show',
            Carbon::now()->addDay(),
            ['batch' => $batch->id, 'channel' => $channel->id]
        );

        $this->assertSame($expected, $url);

        Carbon::setTestNow();
    }
}
