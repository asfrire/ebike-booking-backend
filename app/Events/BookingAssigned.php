<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingAssigned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public $booking,
        public $assignedRiders
    ) {}

    public function broadcastOn()
    {
        $channels = [];
        foreach ($this->assignedRiders as $rider) {
            $channels[] = new PrivateChannel('rider.' . $rider->id);
        }
        return $channels;
    }

    public function broadcastWith()
    {
        return [
            'booking' => [
                'id' => $this->booking->id,
                'pickup_location' => $this->booking->pickup_location,
                'dropoff_location' => $this->booking->dropoff_location,
                'pax' => $this->booking->pax,
                'total_fare' => $this->booking->total_fare,
                'fare_per_passenger' => $this->booking->fare_per_passenger,
                'expires_at' => now()->addMinutes(3)->toISOString(),
            ],
            'allocated_seats' => $this->assignedRiders->map(function($rider) {
                return [
                    'rider_id' => $rider->id,
                    'allocated_seats' => $rider->allocated_seats,
                ];
            })
        ];
    }

    public function broadcastAs()
    {
        return 'booking.assigned';
    }
}
