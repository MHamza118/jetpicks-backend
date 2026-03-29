<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('full_name')->nullable()->comment("From Sign Up 'Username' / Personal Details 'Username'.");
            $table->string('email')->unique()->comment('From Sign Up / Personal Details.');
            $table->string('password_hash')->comment('From Sign Up / Personal Details.');
            $table->string('phone_number', 50)->nullable()->comment('From Personal Details.');
            $table->string('country', 100)->nullable()->comment('From Personal Details / Settings.');
            $table->json('roles')->comment("Array of roles: ['PICKER', 'ORDERER'] or both. Sourced from Profile screen badges.");
            $table->text('avatar_url')->nullable()->comment('From Profile avatar.');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
