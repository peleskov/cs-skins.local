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
            // Снимок данных из inventory на момент создания листинга
            $table->string('inventory_item_name')->nullable()->after('market_hash_name');
            $table->string('inventory_type')->nullable()->after('inventory_item_name');
            $table->string('inventory_icon_url')->nullable()->after('inventory_type');
            $table->json('inventory_tags')->nullable()->after('inventory_icon_url');
            $table->json('inventory_descriptions')->nullable()->after('inventory_tags');
            $table->string('steam_class_id')->nullable()->after('steam_asset_id');
            $table->string('steam_instance_id')->nullable()->after('steam_class_id');
            $table->boolean('tradable')->default(true)->after('inventory_descriptions');
            $table->boolean('marketable')->default(true)->after('tradable');
            
            // Индексы для поиска
            $table->index(['inventory_type', 'status']);
            $table->index(['tradable', 'marketable', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            // Удаляем индексы
            $table->dropIndex(['inventory_type', 'status']);
            $table->dropIndex(['tradable', 'marketable', 'status']);
            
            // Удаляем поля
            $table->dropColumn([
                'inventory_item_name',
                'inventory_type', 
                'inventory_icon_url',
                'inventory_tags',
                'inventory_descriptions',
                'steam_class_id',
                'steam_instance_id',
                'tradable',
                'marketable'
            ]);
        });
    }
};
