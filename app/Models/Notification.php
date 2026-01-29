<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'type',
        'entity_id',
        'title',
        'message',
        'data',
        'is_read',
        'read_at',
        'notification_shown_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'bool',
        'read_at' => 'datetime',
        'notification_shown_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'is_read' => false,
        'notification_shown_at' => null,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
