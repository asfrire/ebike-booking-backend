<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rider extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'is_online',
        'queue_position',
        'capacity',
    ];

    protected $casts = [
        'is_online' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bookingRiders()
    {
        return $this->hasMany(BookingRider::class);
    }

    public function riderSessions()
    {
        return $this->hasMany(RiderSession::class);
    }

    public function activeSession()
    {
        return $this->hasOne(RiderSession::class)->whereNull('time_out');
    }

    public function activeBookings()
    {
        return $this->bookingRiders()
            ->whereIn('status', ['assigned', 'accepted'])
            ->with('booking');
    }

    public function isAvailable()
    {
        return $this->is_online && $this->activeBookings()->count() === 0;
    }

    public function scopeOnline($query)
    {
        return $query->where('is_online', true);
    }

    public function scopeAvailable($query)
    {
        return $query->online()->whereDoesntHave('bookingRiders', function ($q) {
            $q->whereIn('status', ['assigned', 'accepted']);
        });
    }

    public function scopeByQueuePosition($query)
    {
        return $query->orderBy('queue_position', 'asc');
    }

    public function getIsAvailableAttribute()
    {
        return $this->is_online && !$this->bookingRiders()->whereIn('status', ['assigned', 'accepted'])->exists();
    }
}
