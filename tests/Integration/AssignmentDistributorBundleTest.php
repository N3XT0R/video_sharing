<?php

namespace Tests\Integration;

use App\Models\{Assignment, Clip, Video};
use App\Services\AssignmentDistributor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentDistributorBundleTest extends TestCase
{
    use RefreshDatabase;

    public function test_bundle_videos_are_assigned_together(): void
    {
        $v1 = Video::create(['hash' => 'h1', 'path' => 'p1']);
        $v2 = Video::create(['hash' => 'h2', 'path' => 'p2']);
        Clip::create(['video_id' => $v1->id, 'bundle_key' => 'B']);
        Clip::create(['video_id' => $v2->id, 'bundle_key' => 'B']);

        $result = (new AssignmentDistributor())->distribute();

        $this->assertSame(2, $result['assigned']);
        $assignments = Assignment::query()->whereIn('video_id', [$v1->id, $v2->id])->get();
        $this->assertCount(2, $assignments);
        $this->assertSame(1, $assignments->pluck('channel_id')->unique()->count());
    }
}
