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
        Schema::create('client_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            
            // Данные из Steam API
            $table->string('steam_asset_id')->index(); // Уникальный ID экземпляра предмета
            $table->string('steam_class_id'); // Class ID из Steam
            $table->string('steam_instance_id'); // Instance ID из Steam
            $table->string('market_hash_name'); // Полное название с состоянием
            $table->string('item_name'); // Базовое название предмета
            $table->string('type')->nullable(); // Тип предмета из Steam
            $table->string('icon_url')->nullable(); // URL иконки
            $table->boolean('tradable')->default(false);
            $table->boolean('marketable')->default(false);
            $table->integer('amount')->default(1);
            
            // Дополнительные данные
            $table->decimal('float_value', 10, 8)->nullable(); // Износ предмета
            $table->integer('pattern_index')->nullable(); // Паттерн
            $table->json('stickers')->nullable(); // Стикеры на предмете
            $table->string('inspect_url')->nullable(); // Ссылка для инспекта
            $table->json('tags')->nullable(); // Теги из Steam API
            $table->json('descriptions')->nullable(); // Описания из Steam API
            
            // Связь со справочником (опционально)
            $table->foreignId('item_id')->nullable()->constrained('items')->onDelete('set null');
            
            // Метаданные
            $table->timestamp('cached_at'); // Когда получен из Steam
            $table->timestamps();
            
            // Индексы
            $table->unique(['client_id', 'steam_asset_id']);
            $table->index(['client_id', 'item_name']);
            $table->index(['client_id', 'tradable']);
            $table->index(['client_id', 'marketable']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_inventory_items');
    }
};