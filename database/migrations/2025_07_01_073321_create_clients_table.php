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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique()->nullable();
            $table->string('steam_id')->unique();
            $table->string('steam_avatar')->nullable();
            $table->string('steam_trade_url')->nullable();
            $table->decimal('balance', 10, 2)->default(0);
            $table->string('payment_password')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_bot')->default(false);
            $table->enum('locale', ['ru', 'en'])->default('ru');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
