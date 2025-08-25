<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Filament\Pages\VideoUpload;
use App\Jobs\ProcessUploadedVideo;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Tests\DatabaseTestCase;

final class VideoUploadTest extends DatabaseTestCase
{
    public function testSubmitDispatchesJobForEachClip(): void
    {
        Bus::fake();

        $user = User::factory()->create(['name' => 'Tester']);
        $this->actingAs($user);

        $path1 = storage_path('app/uploads/tmp/file1.mp4');
        $path2 = storage_path('app/uploads/tmp/file2.mov');
        @mkdir(dirname($path1), 0777, true);
        file_put_contents($path1, 'a');
        file_put_contents($path2, 'b');

        $file1 = new class($path1)
        {
            public function __construct(private string $path) {}

            public function store($dir): string
            {
                return 'uploads/tmp/'.basename($this->path);
            }

            public function getClientOriginalName(): string
            {
                return 'one.mp4';
            }

            public function getClientOriginalExtension(): string
            {
                return 'mp4';
            }
        };

        $file2 = new class($path2)
        {
            public function __construct(private string $path) {}

            public function store($dir): string
            {
                return 'uploads/tmp/'.basename($this->path);
            }

            public function getClientOriginalName(): string
            {
                return 'two.mov';
            }

            public function getClientOriginalExtension(): string
            {
                return 'mov';
            }
        };

        $state = [
            'clips' => [
                [
                    'file' => $file1,
                    'start_sec' => 1,
                    'end_sec' => 3,
                    'note' => 'first',
                    'bundle_key' => 'B1',
                    'role' => 'R1',
                ],
                [
                    'file' => $file2,
                    'start_sec' => 2,
                    'end_sec' => 4,
                    'note' => 'second',
                    'bundle_key' => 'B2',
                    'role' => 'R2',
                ],
            ],
        ];

        $page = new VideoUpload();
        $page->form = new class($state)
        {
            public function __construct(private array $state) {}

            public function getState(): array
            {
                return $this->state;
            }

            public function fill(): void
            {
            }
        };

        $page->submit();

        Bus::assertDispatchedTimes(ProcessUploadedVideo::class, 2);
        Bus::assertDispatched(ProcessUploadedVideo::class, function (ProcessUploadedVideo $job) use ($user, $file1) {
            return $job->originalName === $file1->getClientOriginalName()
                && $job->ext === $file1->getClientOriginalExtension()
                && $job->start === 1
                && $job->end === 3
                && $job->note === 'first'
                && $job->bundleKey === 'B1'
                && $job->role === 'R1'
                && $job->submittedBy === $user->name;
        });
        Bus::assertDispatched(ProcessUploadedVideo::class, function (ProcessUploadedVideo $job) use ($user, $file2) {
            return $job->originalName === $file2->getClientOriginalName()
                && $job->ext === $file2->getClientOriginalExtension()
                && $job->start === 2
                && $job->end === 4
                && $job->note === 'second'
                && $job->bundleKey === 'B2'
                && $job->role === 'R2'
                && $job->submittedBy === $user->name;
        });

        @unlink($path1);
        @unlink($path2);
    }
}
