<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Создание объединенной таблицы тегов
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('category_code', 50);           // 'rarity', 'type', 'exterior', etc.
            $table->string('steam_internal_name', 100);    // 'Rarity_Legendary_Weapon'
            $table->string('normalized_value', 50);        // 'classified'
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            // Индексы для быстрого поиска
            $table->index('category_code');
            $table->index('normalized_value');
            $table->index(['category_code', 'steam_internal_name']);
            $table->unique(['category_code', 'steam_internal_name'], 'unique_category_steam_name');
        });

        // 2. Создание таблицы связей market_hash_name с тегами
        Schema::create('market_item_tags', function (Blueprint $table) {
            $table->string('market_hash_name', 200);
            $table->foreignId('tag_id')->constrained('tags')->onDelete('cascade');
            
            $table->primary(['market_hash_name', 'tag_id']);
            $table->index('market_hash_name');
            $table->index('tag_id');
        });

        // 3. Изменяем поле type в listings для категорий предметов
        Schema::table('listings', function (Blueprint $table) {
            // Изменяем существующее поле type с p2p/instant на категории предметов
            $table->string('type', 50)->nullable()->change();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Восстанавливаем поле type в listings
        Schema::table('listings', function (Blueprint $table) {
            $table->string('type', 20)->default('p2p')->change();
        });

        // Удаляем таблицы
        Schema::dropIfExists('market_item_tags');
        Schema::dropIfExists('tags');
    }

};