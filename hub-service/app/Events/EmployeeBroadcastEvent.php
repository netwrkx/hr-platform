<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class EmployeeBroadcastEvent implements ShouldBroadcastNow
{
    public function __construct(
        private string $channelName,
        private string $eventType,
        private array $payload,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new Channel($this->channelName)];
    }

    public function broadcastAs(): string
    {
        return $this->eventType;
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
