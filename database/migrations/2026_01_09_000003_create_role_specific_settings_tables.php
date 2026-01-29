<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create picker settings table
        Schema::create('picker_settings', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->boolean('push_notifications_enabled')->default(true);
            $table->boolean('in_app_notifications_enabled')->default(true);
            $table->boolean('message_notifications_enabled')->default(true);
            $table->boolean('location_services_enabled')->default(true);
            $table->string('translation_language')->default('English');
            $table->boolean('auto_translate_messages')->default(false);
            $table->boolean('show_original_and_translated')->default(true);
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique('user_id');
        });

        // Create orderer settings table
        Schema::create('orderer_settings', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->boolean('push_notifications_enabled')->default(true);
            $table->boolean('in_app_notifications_enabled')->default(true);
            $table->boolean('message_notifications_enabled')->default(true);
            $table->boolean('location_services_enabled')->default(true);
            $table->string('translation_language')->default('English');
            $table->boolean('auto_translate_messages')->default(false);
            $table->boolean('show_original_and_translated')->default(true);
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orderer_settings');
        Schema::dropIfExists('picker_settings');
    }
};
