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
            // Сначала удаляем внешний ключ
            $table->dropForeign(['item_id']);
            
            // Делаем поле nullable
            $table->foreignId('item_id')->nullable()->change();
            
            // Восстанавливаем внешний ключ с возможностью null
            $table->foreign('item_id')->references('id')->on('items')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            // Удаляем внешний ключ
            $table->dropForeign(['item_id']);
            
            // Возвращаем поле как NOT NULL (нужно сначала обновить все NULL значения)
            $table->foreignId('item_id')->nullable(false)->change();
            
            // Восстанавливаем внешний ключ
            $table->foreign('item_id')->references('id')->on('items');
        });
    }
};
