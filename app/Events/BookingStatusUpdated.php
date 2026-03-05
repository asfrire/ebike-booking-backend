<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public $booking,
        public $status,
        public $message = null
    ) {}

    public function broadcastOn()
    {
        return [
            new PrivateChannel('booking.' . $this->booking->id),
            new Channel('admin-dashboard')
        ];
    }

    public function broadcastWith()
    {
        return [
            'booking_id' => $this->booking->id,
            'status' => $this->status,
            'message' => $this->message,
            'timestamp' => now()->toISOString(),
            'total_fare' => $this->booking->total_fare,
            'rider_count' => $this->booking->bookingRiders()->where('status', 'accepted')->count(),
        ];
    }

    public function broadcastAs()
    {
        return 'booking.status.updated';
    }
}
