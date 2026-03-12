<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $table = 'rider_vehicles';

    protected $fillable = [
        'rider_id',
        'model',
        'color',
        'plate_number',
        'capacity',
        'appearance_notes',
    ];

    public function rider()
    {
        return $this->belongsTo(User::class, 'rider_id');
    }
}
