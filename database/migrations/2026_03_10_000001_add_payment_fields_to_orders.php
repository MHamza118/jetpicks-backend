<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('payment_status', ['PENDING', 'PAID', 'FAILED', 'REFUNDED'])->default('PENDING')->after('status')->comment('Payment status for the order');
            $table->string('stripe_payment_intent_id')->nullable()->after('payment_status')->comment('Stripe payment intent ID');
            $table->timestamp('payment_completed_at')->nullable()->after('stripe_payment_intent_id')->comment('When payment was successfully completed');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'stripe_payment_intent_id', 'payment_completed_at']);
        });
    }
};
