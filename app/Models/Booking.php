<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'pickup_location',
        'dropoff_location',
        'pax',
        'remaining_pax',
        'status',
    ];

    protected $casts = [
        'pax' => 'integer',
        'remaining_pax' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
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
        return $this->status === 'accepted';
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending', 'partially_assigned', 'fully_assigned']);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'partially_assigned', 'fully_assigned', 'accepted']);
    }
}
