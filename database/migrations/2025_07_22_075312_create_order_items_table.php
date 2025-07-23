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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('listing_id')->constrained()->onDelete('cascade');
            $table->foreignId('seller_id')->constrained('clients')->onDelete('cascade');
            $table->integer('quantity')->default(1);
            
            // Статус конкретного товара
            $table->enum('status', ['reserved', 'trade_sent', 'completed', 'cancelled'])->default('reserved');
            $table->timestamp('reserved_until')->nullable(); // Время истечения резерва
            $table->timestamp('completed_at')->nullable();   // Время завершения трейда
            $table->timestamp('cancelled_at')->nullable();   // Время отмены
            $table->string('cancellation_reason')->nullable(); // Причина отмены
            $table->string('trade_offer_id')->nullable();    // Steam Trade Offer ID
            
            // Дублируем данные для быстрого доступа без JOINов
            $table->string('item_name');                     // Название скина
            $table->string('item_image_url');                // URL картинки
            $table->decimal('price', 10, 2);                 // Цена на момент покупки
            $table->string('seller_name');                   // Имя продавца
            $table->string('buyer_name');                    // Имя покупателя
            
            $table->timestamps();
            
            // Индексы для быстрого поиска
            $table->index(['order_id', 'seller_id']);
            $table->index(['seller_id', 'status', 'created_at']);
            $table->index(['status', 'reserved_until']); // Для поиска истекших резервов
            $table->index('listing_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
