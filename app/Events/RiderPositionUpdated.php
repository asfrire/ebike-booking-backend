<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RiderPositionUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public $riderId,
        public $newPosition,
        public $oldPosition = null
    ) {}

    public function broadcastOn()
    {
        return new Channel('rider-queue');
    }

    public function broadcastWith()
    {
        return [
            'rider_id' => $this->riderId,
            'old_position' => $this->oldPosition,
            'new_position' => $this->newPosition,
            'timestamp' => now()->toISOString(),
        ];
    }

    public function broadcastAs()
    {
        return 'rider.position.updated';
    }
}
