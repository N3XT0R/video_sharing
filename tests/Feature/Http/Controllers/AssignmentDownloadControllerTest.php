<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Enum\StatusEnum;
use App\Models\Assignment;
use App\Models\Batch;
use App\Models\Channel;
use App\Models\Video;
use App\Services\AssignmentService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\DatabaseTestCase;

final class AssignmentDownloadControllerTest extends DatabaseTestCase
{
    /** Rejects requests without a valid signature (403). */
    public function testRequiresValidSignature(): void
    {
        $batch = Batch::factory()->state(['type' => 'assign'])->create();
        $channel = Channel::factory()->create();
        $video = Video::factory()->create([
            'disk' => 'local',
            'path' => 'videos/foo.mp4',
        ]);

        $assignment = Assignment::factory()
            ->for($batch, 'batch')
            ->for($channel, 'channel')
            ->for($video, 'video')
            ->create([
                'status' => StatusEnum::NOTIFIED->value,
                'expires_at' => now()->addHour(),
                'download_token' => hash('sha256', 'secret'),
            ]);

        // Unsigned route => should be forbidden
        $this->get(route('assignments.download', $assignment))
            ->assertStatus(403);
    }

    /** Returns 410 when status is not 'notified' (even if not expired). */
    public function testReturns410WhenWrongStatus(): void
    {
        $batch = Batch::factory()->state(['type' => 'assign'])->create();
        $channel = Channel::factory()->create();
        $video = Video::factory()->create([
            'disk' => 'local',
            'path' => 'videos/bar.mp4',
        ]);

        $assignment = Assignment::factory()
            ->for($batch, 'batch')
            ->for($channel, 'channel')
            ->for($video, 'video')
            ->create([
                'status' => StatusEnum::QUEUED->value, // not 'notified'
                'expires_at' => now()->addHour(),
                'download_token' => hash('sha256', 't-ok'),
            ]);

        $url = URL::temporarySignedRoute('assignments.download', now()->addHour(), [
            'assignment' => $assignment->getKey(),
            't' => 't-ok',
        ]);

        $this->get($url)->assertStatus(410);
    }

    /** Returns 410 when assignment has already expired (even if URL signature is still valid). */
    public function testReturns410WhenExpired(): void
    {
        $batch = Batch::factory()->state(['type' => 'assign'])->create();
        $channel = Channel::factory()->create();
        $video = Video::factory()->create([
            'disk' => 'local',
            'path' => 'videos/baz.mp4',
        ]);

        $assignment = Assignment::factory()
            ->for($batch, 'batch')
            ->for($channel, 'channel')
            ->for($video, 'video')
            ->create([
                'status' => StatusEnum::NOTIFIED->value,
                'expires_at' => now()->subMinute(), // already expired
                'download_token' => hash('sha256', 't-ok'),
            ]);

        $url = URL::temporarySignedRoute('assignments.download', now()->addHour(), [
            'assignment' => $assignment->getKey(),
            't' => 't-ok',
        ]);

        $this->get($url)->assertStatus(410);
    }

    /** Returns 403 when the 't' token does not match the stored hashed token. */
    public function testReturns403OnInvalidToken(): void
    {
        $batch = Batch::factory()->state(['type' => 'assign'])->create();
        $channel = Channel::factory()->create();
        $video = Video::factory()->create([
            'disk' => 'local',
            'path' => 'videos/qux.mp4',
        ]);

        $assignment = Assignment::factory()
            ->for($batch, 'batch')
            ->for($channel, 'channel')
            ->for($video, 'video')
            ->create([
                'status' => StatusEnum::NOTIFIED->value,
                'expires_at' => now()->addHour(),
                'download_token' => hash('sha256', 'GOOD'),
            ]);

        // Signed URL carries a different 't' => should be rejected
        $url = URL::temporarySignedRoute('assignments.download', now()->addHour(), [
            'assignment' => $assignment->getKey(),
            't' => 'BAD',
        ]);

        $this->get($url)->assertStatus(403);
    }

    /** Returns 404 when the video file does not exist on the configured disk. */
    public function testReturns404WhenFileMissing(): void
    {
        Storage::fake('local'); // no file created -> exists() will be false

        $batch = Batch::factory()->state(['type' => 'assign'])->create();
        $channel = Channel::factory()->create();
        $video = Video::factory()->create([
            'disk' => 'local',
            'path' => 'videos/missing.mp4',
        ]);

        $assignment = Assignment::factory()
            ->for($batch, 'batch')
            ->for($channel, 'channel')
            ->for($video, 'video')
            ->create([
                'status' => StatusEnum::NOTIFIED->value,
                'expires_at' => now()->addHour(),
                'download_token' => hash('sha256', 't-ok'),
            ]);

        $url = URL::temporarySignedRoute('assignments.download', now()->addHour(), [
            'assignment' => $assignment->getKey(),
            't' => 't-ok',
        ]);

        $this->get($url)->assertStatus(404);
    }

    /**
     * Happy-path: streams the file (200) with correct headers, marks the assignment as picked up,
     * and stores a Download record with IP and user-agent.
     */
    public function testStreamsFileAndMarksDownloaded(): void
    {
        // Arrange a real file on the 'local' disk
        Storage::fake('local');
        $content = 'HELLO';
        Storage::disk('local')->put('videos/ok.mp4', $content);

        $batch = Batch::factory()->state(['type' => 'assign'])->create();
        $channel = Channel::factory()->create();
        $video = Video::factory()->create([
            'disk' => 'local',
            'path' => 'videos/ok.mp4',
        ]);

        // Start as QUEUED → use real service to prepare (sets NOTIFIED, token, expiry, signed URL)
        $assignment = Assignment::factory()
            ->for($batch, 'batch')
            ->for($channel, 'channel')
            ->for($video, 'video')
            ->create([
                'status' => 'queued',
                'expires_at' => null,
                'download_token' => null,
            ]);

        /** @var AssignmentService $svc */
        $svc = app(AssignmentService::class);
        $url = $svc->prepareDownload($assignment, 2); // +2h, returns signed URL incl. 't'

        // Act: perform the download request
        $resp = $this->withHeaders(['User-Agent' => 'PHPUnit'])
            ->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'video/mp4')
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('ETag', $video->hash)
            ->assertHeader('Content-Disposition', 'attachment; filename="ok.mp4"')
            ->assertHeader('Content-Length', (string)strlen($content));

        // Assert DB side-effects: assignment marked as picked up, and a download row created
        $this->assertDatabaseHas('assignments', [
            'id' => $assignment->getKey(),
            'status' => StatusEnum::PICKEDUP->value,
        ]);

        $this->assertDatabaseHas('downloads', [
            'assignment_id' => $assignment->getKey(),
            'ip' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);
    }
}
