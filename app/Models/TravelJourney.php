<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TravelJourney extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'departure_country',
        'departure_city',
        'departure_date',
        'arrival_country',
        'arrival_city',
        'arrival_date',
        'luggage_weight_capacity',
        'is_active',
    ];

    protected $casts = [
        'departure_date' => 'date',
        'arrival_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
