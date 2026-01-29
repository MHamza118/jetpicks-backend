<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids;

    protected $fillable = [
        'full_name',
        'email',
        'password_hash',
        'phone_number',
        'country',
        'roles',
        'avatar_url',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'roles' => 'array',
        ];
    }
    
    public function getAuthPasswordName()
    {
        return 'password_hash';
    }

    public function languages(): HasMany
    {
        return $this->hasMany(UserLanguage::class);
    }

    public function pickerSettings(): HasOne
    {
        return $this->hasOne(PickerSetting::class);
    }

    public function ordererSettings(): HasOne
    {
        return $this->hasOne(OrdererSetting::class);
    }

    public function travelJourneys(): HasMany
    {
        return $this->hasMany(TravelJourney::class);
    }

    public function ordersAsOrderer(): HasMany
    {
        return $this->hasMany(Order::class, 'orderer_id');
    }

    public function ordersAsPicker(): HasMany
    {
        return $this->hasMany(Order::class, 'assigned_picker_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class, 'offered_by_user_id');
    }
    
    public function reviewsWritten(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }
    
    public function reviewsReceived(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewee_id');
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function payoutMethods(): HasMany
    {
        return $this->hasMany(PayoutMethod::class);
    }

    public function chatRoomsAsOrderer(): HasMany
    {
        return $this->hasMany(ChatRoom::class, 'orderer_id');
    }

    public function chatRoomsAsPicker(): HasMany
    {
        return $this->hasMany(ChatRoom::class, 'picker_id');
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'sender_id');
    }

    public function tipsGiven(): HasMany
    {
        return $this->hasMany(Tip::class, 'from_user_id');
    }

    public function tipsReceived(): HasMany
    {
        return $this->hasMany(Tip::class, 'to_user_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }
}
