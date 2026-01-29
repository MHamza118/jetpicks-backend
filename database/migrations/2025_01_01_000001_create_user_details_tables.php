<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_languages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->string('language_name', 50)->comment("e.g., 'English', 'Spanish'");
        });

        Schema::create('user_settings', function (Blueprint $table) {
            $table->foreignUuid('user_id')->primary()->constrained('users')->onDelete('cascade');
            $table->boolean('push_notifications_enabled')->default(true)->comment("From 'Push Notification' toggle");
            $table->boolean('in_app_notifications_enabled')->default(true)->comment("From 'In app notifications' toggle");
            $table->boolean('message_notifications_enabled')->default(true)->comment("From 'Messages' toggle");
            $table->boolean('location_services_enabled')->default(true)->comment("From 'Location' toggle");
            $table->string('translation_language', 50)->default('English')->comment("From 'Translation language' dropdown");
            $table->boolean('auto_translate_messages')->default(false)->comment("From 'Translate incoming messages automatically' toggle");
            $table->boolean('show_original_and_translated')->default(true)->comment("From 'Show original + translated text' toggle");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
        Schema::dropIfExists('user_languages');
    }
};
