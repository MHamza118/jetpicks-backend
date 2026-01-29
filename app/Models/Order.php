<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'orderer_id',
        'assigned_picker_id',
        'origin_country',
        'origin_city',
        'destination_country',
        'destination_city',
        'special_notes',
        'reward_amount',
        'currency',
        'status',
        'delivered_at',
        'delivery_confirmed_at',
        'delivery_issue_reported',
        'auto_confirmed',
        'accepted_at',
    ];

    protected $casts = [
        'reward_amount' => 'decimal:2',
        'delivery_issue_reported' => 'boolean',
        'auto_confirmed' => 'boolean',
        'delivered_at' => 'datetime',
        'delivery_confirmed_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function orderer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'orderer_id');
    }

    public function picker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_picker_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function chatRoom(): HasOne
    {
        return $this->hasOne(ChatRoom::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function tips(): HasMany
    {
        return $this->hasMany(Tip::class);
    }
}
