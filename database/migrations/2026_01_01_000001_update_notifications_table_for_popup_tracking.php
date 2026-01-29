<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Add entity_id to track the related object (order_id, offer_id, etc.)
            $table->uuid('entity_id')->nullable()->after('type');
            
            // Add notification_shown_at to track when popup was displayed
            $table->timestamp('notification_shown_at')->nullable()->after('is_read');
            
            // Add updated_at for tracking updates
            $table->timestamp('updated_at')->useCurrent()->after('created_at');
            
            // Add index for pending notifications query (is_read = false AND notification_shown_at IS NULL)
            $table->index(['user_id', 'notification_shown_at']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'notification_shown_at']);
            $table->dropColumn(['entity_id', 'notification_shown_at', 'updated_at']);
        });
    }
};
