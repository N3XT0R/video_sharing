<?php

namespace Tests\Integration\Services;

use App\Models\Video;
use App\Services\AssignmentDistributor;
use Tests\DatabaseTestCase;

class AssignmentDistributorInitialRunTest extends DatabaseTestCase
{

    public function test_distributor_handles_initial_run_without_previous_batch(): void
    {
        $video = Video::create(['hash' => 'h1', 'path' => 'p1']);

        $result = (new AssignmentDistributor)->distribute();

        $this->assertSame(['assigned' => 1, 'skipped' => 0], $result);
        $this->assertDatabaseHas('assignments', ['video_id' => $video->id]);
    }
}
