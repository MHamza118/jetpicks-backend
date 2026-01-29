<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignUuid('offered_by_user_id')->constrained('users')->onDelete('cascade')->comment("ID of user making the offer (Orderer or Picker)");
            
            $table->enum('offer_type', ['INITIAL', 'COUNTER'])->comment("INITIAL = Orderer's reward. COUNTER = Picker's counter-offer.");
            $table->decimal('offer_amount', 10, 2)->comment("The amount offered.");
            
            $table->foreignUuid('parent_offer_id')->nullable()->constrained('offers')->nullOnDelete()->comment("Links counter-offers to previous offers.");
            
            $table->enum('status', ['PENDING', 'ACCEPTED', 'REJECTED', 'SUPERSEDED'])->default('PENDING');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
