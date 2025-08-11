<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ZipProgressUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $jobId,
        public array $state
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
        return $this->state;
    }
}