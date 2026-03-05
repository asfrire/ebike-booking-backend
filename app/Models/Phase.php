<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Phase extends Model
{
    use HasFactory;

    protected $fillable = [
        'subdivision_id',
        'name',
    ];

    /**
     * Get the subdivision that owns the phase.
     */
    public function subdivision()
    {
        return $this->belongsTo(Subdivision::class);
    }

    /**
     * Get the fares for the phase.
     */
    public function fares()
    {
        return $this->hasMany(Fare::class);
    }

    /**
     * Get the bookings for the phase.
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
