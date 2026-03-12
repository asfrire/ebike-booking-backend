<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiderQueue extends Model
{
    use HasFactory;

    protected $table = 'rider_queue';

    protected $fillable = [
        'rider_id',
        'queue_position',
        'is_online',
        'status',
    ];

    protected $casts = [
        'is_online' => 'boolean',
        'status' => 'string',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function bookingRiders()
    {
        return $this->hasMany(BookingRider::class, 'rider_id', 'rider_id');
    }

    public function riderSessions()
    {
        return $this->hasMany(RiderSession::class, 'rider_id', 'rider_id');
    }

    public function activeSession()
    {
        return $this->hasOne(RiderSession::class, 'rider_id', 'rider_id')->whereNull('time_out');
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
        return $query->orderByRaw("CASE WHEN queue_position = 'stand by' THEN 99999 ELSE CAST(queue_position AS INTEGER) END ASC");
    }

    public function isAvailable()
    {
        return $this->is_online && !$this->bookingRiders()->whereIn('status', ['assigned', 'accepted'])->exists();
    }
}
