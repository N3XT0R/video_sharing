<?php

namespace Tests\Integration;

use App\Models\Video;
use App\Services\AssignmentDistributor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentDistributorInitialRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_distributor_handles_initial_run_without_previous_batch(): void
    {
        $video = Video::create(['hash' => 'h1', 'path' => 'p1']);

        $result = (new AssignmentDistributor)->distribute();

        $this->assertSame(['assigned' => 1, 'skipped' => 0], $result);
        $this->assertDatabaseHas('assignments', ['video_id' => $video->id]);
    }
}
