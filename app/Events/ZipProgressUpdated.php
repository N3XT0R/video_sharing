<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ZipProgressUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $jobId,
        public string $status,
        public int $progress,
        public ?string $name = null,
    ) {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("zip.{$this->jobId}");
    }

    public function broadcastAs(): string
    {
        return 'zip.progress';
    }

    public function broadcastWith(): array
    {
        return [
            'status' => $this->status,
            'progress' => $this->progress,
            'name' => $this->name,
        ];
    }
}
