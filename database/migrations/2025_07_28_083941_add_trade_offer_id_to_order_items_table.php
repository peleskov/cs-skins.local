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
        Schema::table('order_items', function (Blueprint $table) {
            // Удаляем существующую колонку если она есть
            if (Schema::hasColumn('order_items', 'trade_offer_id')) {
                $table->dropColumn('trade_offer_id');
            }
        });
        
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('trade_offer_id')->nullable()->constrained('trade_offers')->onDelete('set null');
            $table->index('trade_offer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['trade_offer_id']);
            $table->dropColumn('trade_offer_id');
        });
    }
};
