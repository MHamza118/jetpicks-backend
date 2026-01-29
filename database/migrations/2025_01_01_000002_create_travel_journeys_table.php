<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_journeys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->string('departure_country', 100);
            $table->string('departure_city', 100);
            $table->date('departure_date');
            $table->string('arrival_country', 100);
            $table->string('arrival_city', 100);
            $table->date('arrival_date');
            $table->string('luggage_weight_capacity', 50)->comment("From 'Luggage' field (e.g. '10kg')");
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_journeys');
    }
};
