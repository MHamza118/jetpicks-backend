<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('orderer_id')->constrained('users')->onDelete('cascade')->comment('The user who created the order.');
            $table->foreignUuid('assigned_picker_id')->nullable()->constrained('users')->onDelete('set null')->comment('The picker who accepted the order. NULL until accepted.');
            
            $table->string('origin_country', 100)->comment('From Step 1: Delivery Route');
            $table->string('origin_city', 100)->comment('From Step 1: Delivery Route');
            $table->string('destination_country', 100)->comment('From Step 1: Delivery Route');
            $table->string('destination_city', 100)->comment('From Step 1: Delivery Route');
            
            $table->text('special_notes')->nullable()->comment('From Step 1: Special Notes (Optional)');
            $table->decimal('reward_amount', 10, 2)->comment('From Step 3: Enter Delivery Reward. Initial offer price.');
            $table->string('currency', 3)->nullable();
            $table->integer('waiting_days')->nullable()->comment('From Step 2: How long can you wait for your items in days');
            
            $table->enum('status', ['DRAFT', 'PENDING', 'ACCEPTED', 'DELIVERED', 'COMPLETED', 'CANCELLED'])->default('DRAFT')->comment("DRAFT (Being created), PENDING (Open), ACCEPTED (Picker assigned), DELIVERED (Marked by picker), COMPLETED (Confirmed by Orderer), CANCELLED.");
            
            $table->timestamp('delivered_at')->nullable()->comment("When picker marked as delivered. Starts 48h timer.");
            $table->timestamp('delivery_confirmed_at')->nullable()->comment("When orderer confirmed delivery or auto-confirmed.");
            $table->boolean('delivery_issue_reported')->default(false)->comment("From View Order Details 'Issue with delivery' option.");
            $table->boolean('auto_confirmed')->default(false)->comment("True if confirmed automatically after 48 hours.");
            
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('accepted_at')->nullable();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->onDelete('cascade');
            
            $table->string('item_name', 255)->comment("From 'Item Name' field");
            $table->string('weight', 50)->nullable()->comment("From 'Weight' field");
            $table->decimal('price', 10, 2)->comment("From 'Price of item' field");
            $table->string('currency', 3)->nullable()->comment("Currency code for the item price (e.g., USD, EUR, GBP)");
            $table->integer('quantity')->default(1)->comment("From 'Quantity' field");
            $table->text('special_notes')->nullable()->comment("From 'Special notes' field in Step 2");
            $table->text('store_link')->nullable()->comment("From 'Store' field in Step 4/Summary. Matches 'Store link' in Picker view.");
            $table->json('product_images')->nullable()->comment("Array of image URLs uploaded in Step 2.");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
