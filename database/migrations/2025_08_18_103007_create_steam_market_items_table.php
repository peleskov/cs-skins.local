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
        Schema::create('steam_market_items', function (Blueprint $table) {
            $table->id();
            $table->string('market_hash_name')->unique();
            $table->bigInteger('item_nameid')->nullable()->unique();
            $table->timestamp('last_price_update')->nullable();
            $table->timestamps();
            
            $table->index('market_hash_name');
            $table->index('item_nameid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('steam_market_items');
    }
};
