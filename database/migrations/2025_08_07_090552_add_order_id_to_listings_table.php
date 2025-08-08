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
        Schema::table('listings', function (Blueprint $table) {
            // Удаляем старый уникальный индекс
            $table->dropUnique('unique_steam_asset_seller');
            
            // Создаем новый уникальный индекс с sold_at
            $table->unique(['steam_asset_id', 'seller_id', 'sold_at'], 'unique_steam_asset_seller_sold_at');
            
            // Обновляем обычный индекс
            $table->dropIndex('idx_steam_asset_seller_status');
            $table->index(['steam_asset_id', 'seller_id', 'sold_at', 'status'], 'idx_steam_asset_seller_sold_at_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            // Удаляем новые индексы
            $table->dropUnique('unique_steam_asset_seller_sold_at');
            $table->dropIndex('idx_steam_asset_seller_sold_at_status');
            
            // Восстанавливаем старые индексы
            $table->unique(['steam_asset_id', 'seller_id'], 'unique_steam_asset_seller');
            $table->index(['steam_asset_id', 'seller_id', 'status'], 'idx_steam_asset_seller_status');
        });
    }
};