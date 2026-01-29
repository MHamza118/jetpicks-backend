<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_journeys', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('luggage_weight_capacity');
        });
    }

    public function down(): void
    {
        Schema::table('travel_journeys', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
