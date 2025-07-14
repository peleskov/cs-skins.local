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
            // Steam интеграция
            $table->string('steam_asset_id')->nullable()->after('buyer_id');
            $table->string('steam_owner_id')->nullable()->after('steam_asset_id');
            $table->string('market_hash_name')->nullable()->after('steam_owner_id');
            $table->string('currency', 10)->default('RUB')->after('price');
            $table->string('type', 20)->default('p2p')->after('currency'); // p2p, bot
            $table->string('wear_condition')->nullable()->after('type');
            $table->decimal('float_value', 10, 8)->nullable()->after('wear_condition');
            $table->text('inspect_url')->nullable()->after('float_value');
            
            // Индексы для быстрого поиска
            $table->index(['steam_asset_id', 'seller_id', 'status']);
            $table->index(['market_hash_name', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex(['steam_asset_id', 'seller_id', 'status']);
            $table->dropIndex(['market_hash_name', 'status']);
            
            $table->dropColumn([
                'steam_asset_id',
                'steam_owner_id', 
                'market_hash_name',
                'currency',
                'type',
                'wear_condition',
                'float_value',
                'inspect_url'
            ]);
        });
    }
};
