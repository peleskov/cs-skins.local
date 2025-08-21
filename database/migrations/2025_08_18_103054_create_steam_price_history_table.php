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
        Schema::create('steam_price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('steam_market_item_id')->constrained('steam_market_items')->onDelete('cascade');
            $table->date('date');
            $table->decimal('price', 10, 2);
            $table->integer('volume')->default(0);
            $table->timestamps();
            
            $table->index(['steam_market_item_id', 'date']);
            $table->unique(['steam_market_item_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('steam_price_history');
    }
};
