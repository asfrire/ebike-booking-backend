<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'subdivision_id',
        'phase_id',
        'pickup_location',
        'dropoff_location',
        'block_number',
        'lot_number',
        'pax',
        'remaining_pax',
        'status',
        'total_fare',
        'platform_fee',
        'rider_earning',
        'fare_per_passenger',
    ];

    protected $casts = [
        'pax' => 'integer',
        'remaining_pax' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function subdivision()
    {
        return $this->belongsTo(Subdivision::class);
    }

    public function phase()
    {
        return $this->belongsTo(Phase::class);
    }

    public function bookingRiders()
    {
        return $this->hasMany(BookingRider::class);
    }

    public function riders()
    {
        return $this->belongsToMany(Rider::class, 'booking_riders')
            ->withPivot(['allocated_seats', 'status', 'expires_at'])
            ->withTimestamps();
    }

    public function assignedRiders()
    {
        return $this->bookingRiders()->where('status', 'assigned');
    }

    public function acceptedRiders()
    {
        return $this->bookingRiders()->where('status', 'accepted');
    }

    public function isFullyAssigned()
    {
        return $this->remaining_pax === 0;
    }

    public function isAccepted()
    {
        return $this->status === 'waiting';
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'waiting', 'on_ride']);
    }
}
