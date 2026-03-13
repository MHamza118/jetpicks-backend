<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->onDelete('cascade')->comment('Associated order');
            $table->foreignUuid('user_id')->nullable()->constrained('users')->onDelete('cascade')->comment('User who made the payment');
            
            $table->string('stripe_payment_intent_id')->unique()->comment('Stripe Payment Intent ID');
            $table->decimal('amount', 10, 2)->comment('Payment amount');
            $table->string('currency', 3)->comment('Currency code (e.g., USD, EUR)');
            
            $table->enum('status', ['PENDING', 'PROCESSING', 'SUCCEEDED', 'FAILED', 'CANCELED', 'REFUNDED'])->default('PENDING')->comment('Payment status');
            $table->string('payment_method_type')->nullable()->comment('Type of payment method (card, paypal, etc.)');
            $table->string('card_last_four')->nullable()->comment('Last 4 digits of card');
            $table->string('card_brand')->nullable()->comment('Card brand (visa, mastercard, etc.)');
            
            $table->text('metadata')->nullable()->comment('Additional payment metadata as JSON');
            $table->text('error_message')->nullable()->comment('Error message if payment failed');
            
            $table->timestamp('paid_at')->nullable()->comment('When payment was successfully completed');
            $table->timestamp('failed_at')->nullable()->comment('When payment failed');
            $table->timestamp('refunded_at')->nullable()->comment('When payment was refunded');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
