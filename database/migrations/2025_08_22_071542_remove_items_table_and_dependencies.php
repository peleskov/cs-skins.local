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
        // Отключаем проверку foreign keys
        Schema::disableForeignKeyConstraints();
        
        // Удаляем поле item_id из таблицы listings если оно существует
        if (Schema::hasColumn('listings', 'item_id')) {
            Schema::table('listings', function (Blueprint $table) {
                // Пытаемся удалить foreign key если он существует
                try {
                    $table->dropForeign(['item_id']);
                } catch (\Exception $e) {
                    // Игнорируем если foreign key не существует
                }
                $table->dropColumn('item_id');
            });
        }

        // Удаляем поле item_id из таблицы client_inventory_items если оно существует
        if (Schema::hasColumn('client_inventory_items', 'item_id')) {
            Schema::table('client_inventory_items', function (Blueprint $table) {
                // Пытаемся удалить foreign key если он существует
                try {
                    $table->dropForeign(['item_id']);
                } catch (\Exception $e) {
                    // Игнорируем если foreign key не существует
                }
                $table->dropColumn('item_id');
            });
        }

        // Удаляем таблицу items
        Schema::dropIfExists('items');
        
        // Включаем обратно проверку foreign keys
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Восстанавливаем таблицу items
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('steam_market_hash_name')->unique();
            $table->string('steam_classid')->nullable();
            $table->string('steam_instanceid')->nullable();
            $table->string('name_ru');
            $table->string('name_en');
            $table->string('type')->nullable();
            $table->string('weapon')->nullable();
            $table->string('rarity')->nullable();
            $table->string('image_url')->nullable();
            $table->string('image_fn')->nullable();
            $table->string('image_mw')->nullable();
            $table->string('image_ft')->nullable();
            $table->string('image_ww')->nullable();
            $table->string('image_bs')->nullable();
            $table->decimal('min_steam_price', 10, 2)->nullable();
            $table->integer('steam_listings_count')->default(0);
            $table->boolean('is_valid')->default(false);
            $table->decimal('buyout_coefficient', 3, 2)->nullable();
            $table->text('description_ru')->nullable();
            $table->text('description_en')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();
            
            $table->index('type');
            $table->index('rarity');
            $table->index('is_valid');
        });

        // Восстанавливаем поле item_id в таблице listings
        Schema::table('listings', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable()->after('id')->constrained('items')->nullOnDelete();
        });

        // Восстанавливаем поле item_id в таблице client_inventory_items
        Schema::table('client_inventory_items', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable()->after('client_id')->constrained('items')->nullOnDelete();
        });
    }
};