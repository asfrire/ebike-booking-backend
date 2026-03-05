<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subdivision extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * Get the phases for the subdivision.
     */
    public function phases()
    {
        return $this->hasMany(Phase::class);
    }

    /**
     * Get the fares for the subdivision.
     */
    public function fares()
    {
        return $this->hasMany(Fare::class);
    }

    /**
     * Get the bookings for the subdivision.
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
