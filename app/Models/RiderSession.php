<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiderSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'rider_id',
        'time_in',
        'time_out',
        'total_minutes',
    ];

    protected $casts = [
        'time_in' => 'datetime',
        'time_out' => 'datetime',
    ];

    /**
     * Get the rider that owns the session.
     */
    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }

    /**
     * Scope to get active sessions (where time_out is null).
     */
    public function scopeActive($query)
    {
        return $query->whereNull('time_out');
    }

    /**
     * Scope to get sessions for a specific date.
     */
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('time_in', $date);
    }

    /**
     * Calculate total minutes for the session.
     */
    public function calculateTotalMinutes()
    {
        if ($this->time_out && $this->time_in) {
            return $this->time_out->diffInMinutes($this->time_in);
        }
        return null;
    }

    /**
     * Get formatted duration.
     */
    public function getFormattedDurationAttribute()
    {
        $minutes = $this->total_minutes;
        
        if ($minutes >= 60) {
            $hours = floor($minutes / 60);
            $remainingMinutes = $minutes % 60;
            return "{$hours}h {$remainingMinutes}m";
        }
        
        return "{$minutes}m";
    }
}
