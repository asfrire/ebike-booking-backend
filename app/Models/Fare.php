<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fare extends Model
{
    use HasFactory;

    protected $fillable = [
        'subdivision_id',
        'phase_id',
        'fare_per_passenger',
    ];

    protected $casts = [
        'fare_per_passenger' => 'decimal:2',
    ];

    /**
     * Get the subdivision that owns the fare.
     */
    public function subdivision()
    {
        return $this->belongsTo(Subdivision::class);
    }

    /**
     * Get the phase that owns the fare.
     */
    public function phase()
    {
        return $this->belongsTo(Phase::class);
    }

    /**
     * Get formatted fare amount.
     */
    public function getFormattedFareAttribute()
    {
        return '₱' . number_format($this->fare_per_passenger, 2);
    }

    /**
     * Scope to get fare by subdivision and phase.
     */
    public function scopeForSubdivisionAndPhase($query, $subdivisionId, $phaseId)
    {
        return $query->where('subdivision_id', $subdivisionId)
                   ->where('phase_id', $phaseId);
    }
}
