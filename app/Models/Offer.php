<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offer extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'offered_by_user_id',
        'offer_type',
        'offer_amount',
        'parent_offer_id',
        'status',
    ];

    protected $casts = [
        'offer_amount' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function offeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'offered_by_user_id');
    }

    public function parentOffer(): BelongsTo
    {
        return $this->belongsTo(Offer::class, 'parent_offer_id');
    }

    public function childOffers(): HasMany
    {
        return $this->hasMany(Offer::class, 'parent_offer_id');
    }
}
