<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Stripe Connect Express account ID for Jetbuyers
            // Created when Jetbuyer completes bank onboarding via Stripe hosted flow
            $table->string('stripe_connect_account_id')->nullable()->after('avatar_url');

            // Verification status from Stripe
            // Values: null (not started) | pending | verified | restricted
            $table->string('stripe_connect_status')->nullable()->after('stripe_connect_account_id');

            // Wallet balance available for withdrawal (in pence/cents)
            // Updated by Stripe webhooks when transfers are made
            $table->unsignedBigInteger('wallet_balance_pence')->default(0)->after('stripe_connect_status');

            // Pending balance (transferred but not yet available)
            $table->unsignedBigInteger('wallet_pending_pence')->default(0)->after('wallet_balance_pence');

            // Currency for the wallet (iso code, lowercase: gbp, usd, eur)
            $table->string('wallet_currency', 3)->default('gbp')->after('wallet_pending_pence');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_connect_account_id',
                'stripe_connect_status',
                'wallet_balance_pence',
                'wallet_pending_pence',
                'wallet_currency',
            ]);
        });
    }
};
