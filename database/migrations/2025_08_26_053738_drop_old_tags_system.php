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
        // 1. Удаляем внешние ключи из таблицы listings
        if (Schema::hasTable('listings')) {
            Schema::table('listings', function (Blueprint $table) {
                if (Schema::hasColumn('listings', 'type_id')) {
                    $table->dropForeign(['type_id']);
                    $table->dropForeign(['quality_id']);
                    $table->dropForeign(['rarity_id']);
                    $table->dropForeign(['exterior_id']);
                }
            });
            
            Schema::table('listings', function (Blueprint $table) {
                if (Schema::hasColumn('listings', 'type_id')) {
                    $table->dropColumn(['type_id', 'quality_id', 'rarity_id', 'exterior_id']);
                }
                
                // Удаляем дублирующие поля
                if (Schema::hasColumn('listings', 'inventory_type')) {
                    $table->dropIndex('listings_inventory_type_status_index');
                    $table->dropColumn('inventory_type');
                }
                
                if (Schema::hasColumn('listings', 'inventory_tags')) {
                    $table->dropColumn('inventory_tags');
                }
            });
        }

        // 2. Удаляем внешние ключи из таблицы client_inventory_items
        if (Schema::hasTable('client_inventory_items')) {
            Schema::table('client_inventory_items', function (Blueprint $table) {
                if (Schema::hasColumn('client_inventory_items', 'type_id')) {
                    $table->dropForeign(['type_id']);
                    $table->dropForeign(['quality_id']);
                    $table->dropForeign(['rarity_id']);
                    $table->dropForeign(['exterior_id']);
                }
            });
            
            Schema::table('client_inventory_items', function (Blueprint $table) {
                if (Schema::hasColumn('client_inventory_items', 'type_id')) {
                    $table->dropColumn(['type_id', 'quality_id', 'rarity_id', 'exterior_id']);
                }
                
                // Удаляем дублирующее поле type
                if (Schema::hasColumn('client_inventory_items', 'type')) {
                    $table->dropColumn('type');
                }
            });
        }

        // 3. Удаляем старые таблицы системы тегов
        Schema::dropIfExists('item_tags');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('tag_categories');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Восстановление старой структуры (если потребуется откат)
        
        // 1. Создание таблиц
        Schema::create('tag_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('steam_category', 50);
            $table->boolean('is_primary')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['code']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('tag_categories')->onDelete('cascade');
            $table->string('steam_internal_name', 100);
            $table->string('normalized_value', 50);
            $table->string('steam_localized_name', 100)->nullable();
            $table->string('color', 7)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->useCurrent();
            
            $table->unique(['category_id', 'steam_internal_name'], 'unique_tag');
            $table->index(['category_id']);
            $table->index(['normalized_value']);
        });

        Schema::create('item_tags', function (Blueprint $table) {
            $table->unsignedBigInteger('item_id');
            $table->enum('item_type', ['inventory', 'listing']);
            $table->foreignId('tag_id')->constrained('tags')->onDelete('cascade');
            
            $table->primary(['item_id', 'item_type', 'tag_id']);
            $table->index(['item_id', 'item_type']);
            $table->index(['tag_id']);
        });

        // 2. Восстановление полей в client_inventory_items
        if (Schema::hasTable('client_inventory_items')) {
            Schema::table('client_inventory_items', function (Blueprint $table) {
                $table->string('type', 255)->nullable();
                $table->foreignId('type_id')->nullable()->constrained('tags')->onDelete('set null');
                $table->foreignId('quality_id')->nullable()->constrained('tags')->onDelete('set null');
                $table->foreignId('rarity_id')->nullable()->constrained('tags')->onDelete('set null');
                $table->foreignId('exterior_id')->nullable()->constrained('tags')->onDelete('set null');
                
                $table->index(['type_id']);
                $table->index(['quality_id']);
                $table->index(['rarity_id']);
                $table->index(['exterior_id']);
            });
        }

        // 3. Восстановление полей в listings
        if (Schema::hasTable('listings')) {
            Schema::table('listings', function (Blueprint $table) {
                $table->string('inventory_type', 255)->nullable();
                $table->longText('inventory_tags')->nullable();
                $table->foreignId('type_id')->nullable()->constrained('tags')->onDelete('set null');
                $table->foreignId('quality_id')->nullable()->constrained('tags')->onDelete('set null');
                $table->foreignId('rarity_id')->nullable()->constrained('tags')->onDelete('set null');
                $table->foreignId('exterior_id')->nullable()->constrained('tags')->onDelete('set null');
                
                $table->index('inventory_type');
                $table->index(['type_id']);
                $table->index(['quality_id']);
                $table->index(['rarity_id']);
                $table->index(['exterior_id']);
            });
        }
    }
};