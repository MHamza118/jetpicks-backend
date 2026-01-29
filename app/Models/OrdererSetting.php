<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdererSetting extends Model
{
    use HasFactory;

    protected $table = 'orderer_settings';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'user_id',
        'push_notifications_enabled',
        'in_app_notifications_enabled',
        'message_notifications_enabled',
        'location_services_enabled',
        'translation_language',
        'auto_translate_messages',
        'show_original_and_translated',
    ];

    protected $casts = [
        'push_notifications_enabled' => 'boolean',
        'in_app_notifications_enabled' => 'boolean',
        'message_notifications_enabled' => 'boolean',
        'location_services_enabled' => 'boolean',
        'auto_translate_messages' => 'boolean',
        'show_original_and_translated' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
