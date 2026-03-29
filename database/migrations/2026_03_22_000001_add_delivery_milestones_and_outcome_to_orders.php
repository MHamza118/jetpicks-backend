<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // ── Delivery milestone tracking ────────────────────────────────
            // Stores the current milestone as an enum string.
            // Values: pending | accepted | payment_secured | items_purchased
            //         | departed | dropped_at_locker | ready_to_meet
            //         | delivered | completed | cancelled
            $table->string('delivery_milestone')->default('pending')->after('status');

            // Timestamps for each milestone (nullable — set when reached)
            $table->timestamp('items_purchased_at')->nullable()->after('delivery_milestone');
            $table->timestamp('departed_at')->nullable()->after('items_purchased_at');
            $table->timestamp('dropped_at_locker_at')->nullable()->after('departed_at');
            $table->timestamp('ready_to_meet_at')->nullable()->after('dropped_at_locker_at');

            // ── Delivery outcome (3-option system) ────────────────────────
            // Values: all_delivered | partial_delivery | unable_to_deliver | null
            $table->string('delivery_outcome')->nullable()->after('ready_to_meet_at');

            // Notes from Jetbuyer on partial or unable outcomes
            // Visible to Jetpicker, Jetbuyer, and JetPicks admin
            $table->text('delivery_notes')->nullable()->after('delivery_outcome');

            // JSON array of per-item delivery statuses for partial delivery
            // Format: [{"item_id": "uuid", "delivered": true}, ...]
            $table->json('item_delivery_statuses')->nullable()->after('delivery_notes');

            // ── Delivery method (chosen on Order Accepted screen) ──────────
            // Values: meet_in_person | inpost_locker | inpost_home
            $table->string('delivery_method')->default('meet_in_person')->after('item_delivery_statuses');

            // InPost locker ID when inpost_locker or inpost_home is chosen
            $table->string('inpost_locker_id')->nullable()->after('delivery_method');

            // InPost shipment tracking number (set when Jetbuyer creates shipment)
            $table->string('inpost_tracking_number')->nullable()->after('inpost_locker_id');

            // Jetpicker home address for inpost_home delivery
            $table->text('delivery_address')->nullable()->after('inpost_tracking_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_milestone',
                'items_purchased_at',
                'departed_at',
                'dropped_at_locker_at',
                'ready_to_meet_at',
                'delivery_outcome',
                'delivery_notes',
                'item_delivery_statuses',
                'delivery_method',
                'inpost_locker_id',
                'inpost_tracking_number',
                'delivery_address',
            ]);
        });
    }
};
