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
        Schema::table('case_items', function (Blueprint $table) {
            // Связь с виртуальными предметами
            $table->foreignId('virtual_item_id')->nullable()->after('tier_id')->constrained('virtual_items')->nullOnDelete();

            // Цена предмета в кейсе (может отличаться от steam_price)
            $table->decimal('price', 10, 2)->nullable()->after('virtual_item_id');
        });

        // inventory_item_id делаем nullable для совместимости
        Schema::table('case_items', function (Blueprint $table) {
            $table->unsignedBigInteger('inventory_item_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('case_items', function (Blueprint $table) {
            $table->dropForeign(['virtual_item_id']);
            $table->dropColumn(['virtual_item_id', 'price']);
        });
    }
};
