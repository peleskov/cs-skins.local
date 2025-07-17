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
            // Добавляем уникальный индекс для steam_asset_id + seller_id
            $table->unique(['steam_asset_id', 'seller_id'], 'unique_steam_asset_seller');
            
            // Добавляем обычный индекс для быстрого поиска по статусу
            $table->index(['steam_asset_id', 'seller_id', 'status'], 'idx_steam_asset_seller_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            // Удаляем новые индексы
            $table->dropUnique('unique_steam_asset_seller');
            $table->dropIndex('idx_steam_asset_seller_status');
            
            // Восстанавливаем старый индекс
            $table->index(['steam_asset_id', 'seller_id', 'status']);
        });
    }
};
