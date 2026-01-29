<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Payout Methods (For Pickers)
        Schema::create('payout_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('method_type', ['BANK_ACCOUNT', 'PAYPAL', 'MOBILE_WALLET']);
            $table->boolean('is_default')->default(false);
            
            $table->string('bank_name', 100)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->string('paypal_email')->nullable();
            $table->string('wallet_type', 100)->nullable();
            $table->string('wallet_mobile_number', 50)->nullable();
            
            $table->timestamp('created_at')->useCurrent();
        });

        // Payment Methods (For Orderers)
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('method_type', ['CREDIT_CARD', 'PAYPAL']);
            $table->boolean('is_default')->default(false);
            
            $table->string('card_holder_name')->nullable()->comment("From 'Name on card'");
            $table->string('card_last_four', 4)->nullable();
            $table->string('card_brand', 50)->nullable()->comment("From 'Card Type'");
            $table->integer('expiry_month')->nullable();
            $table->integer('expiry_year')->nullable();
            $table->text('billing_address')->nullable();
            $table->string('paypal_email')->nullable();
            $table->string('payment_token')->comment("Token from payment gateway, never store full sensitive data.");
            
            $table->timestamp('created_at')->useCurrent();
        });

        // Reviews
        Schema::create('reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignUuid('reviewer_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('reviewee_id')->constrained('users')->onDelete('cascade');
            $table->integer('rating')->comment("1 to 5 stars");
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // Tips
        Schema::create('tips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignUuid('from_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('to_user_id')->constrained('users')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tips');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('payout_methods');
    }
};
