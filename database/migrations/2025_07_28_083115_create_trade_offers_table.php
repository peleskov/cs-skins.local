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
        Schema::create('trade_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('seller_id')->constrained('clients')->onDelete('cascade');
            $table->foreignId('buyer_id')->constrained('clients')->onDelete('cascade');
            $table->string('buyer_trade_url'); // Снимок Trade URL покупателя
            $table->json('asset_ids'); // Массив Steam asset_id предметов
            $table->enum('status', ['pending', 'sent', 'completed', 'cancelled'])->default('pending');
            $table->string('steam_trade_offer_id')->nullable(); // Steam Trade Offer ID после создания
            $table->timestamps();
            
            // Индексы для производительности
            $table->index(['seller_id', 'status']);
            $table->index(['order_id', 'status']);
            $table->index('steam_trade_offer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trade_offers');
    }
};
