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
        // 1. Создание справочников тегов
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
            $table->string('color', 7)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->useCurrent();
            
            $table->unique(['category_id', 'steam_internal_name'], 'unique_tag');
            $table->index(['category_id']);
            $table->index(['normalized_value']);
        });

        // 2. Таблица связей предметов с тегами
        Schema::create('item_tags', function (Blueprint $table) {
            $table->unsignedBigInteger('item_id');
            $table->enum('item_type', ['inventory', 'listing']);
            $table->foreignId('tag_id')->constrained('tags')->onDelete('cascade');
            
            $table->primary(['item_id', 'item_type', 'tag_id']);
            $table->index(['item_id', 'item_type']);
            $table->index(['tag_id']);
        });

        // 3. Добавление основных тегов в inventory_items
        if (Schema::hasTable('client_inventory_items')) {
            Schema::table('client_inventory_items', function (Blueprint $table) {
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

        // 4. Добавление основных тегов в listings
        if (Schema::hasTable('listings')) {
            Schema::table('listings', function (Blueprint $table) {
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

        // 5. Заполнение базовых категорий
        $this->seedBaseCategories();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Удаляем внешние ключи из основных таблиц
        if (Schema::hasTable('listings')) {
            Schema::table('listings', function (Blueprint $table) {
                $table->dropForeign(['type_id']);
                $table->dropForeign(['quality_id']);
                $table->dropForeign(['rarity_id']);
                $table->dropForeign(['exterior_id']);
                $table->dropColumn(['type_id', 'quality_id', 'rarity_id', 'exterior_id']);
            });
        }

        if (Schema::hasTable('client_inventory_items')) {
            Schema::table('client_inventory_items', function (Blueprint $table) {
                $table->dropForeign(['type_id']);
                $table->dropForeign(['quality_id']);
                $table->dropForeign(['rarity_id']);
                $table->dropForeign(['exterior_id']);
                $table->dropColumn(['type_id', 'quality_id', 'rarity_id', 'exterior_id']);
            });
        }

        // Удаляем таблицы тегов
        Schema::dropIfExists('item_tags');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('tag_categories');
    }

    /**
     * Заполнение базовых категорий тегов
     */
    private function seedBaseCategories(): void
    {
        DB::table('tag_categories')->insert([
            [
                'code' => 'type',
                'steam_category' => 'Type',
                'is_primary' => true,
                'sort_order' => 1,
                'created_at' => now()
            ],
            [
                'code' => 'quality',
                'steam_category' => 'Quality',
                'is_primary' => true,
                'sort_order' => 2,
                'created_at' => now()
            ],
            [
                'code' => 'rarity',
                'steam_category' => 'Rarity',
                'is_primary' => true,
                'sort_order' => 3,
                'created_at' => now()
            ],
            [
                'code' => 'exterior',
                'steam_category' => 'Exterior',
                'is_primary' => true,
                'sort_order' => 4,
                'created_at' => now()
            ],
            [
                'code' => 'weapon',
                'steam_category' => 'Weapon',
                'is_primary' => false,
                'sort_order' => 5,
                'created_at' => now()
            ],
            [
                'code' => 'collection',
                'steam_category' => 'ItemSet',
                'is_primary' => false,
                'sort_order' => 6,
                'created_at' => now()
            ],
            [
                'code' => 'tournament',
                'steam_category' => 'Tournament',
                'is_primary' => false,
                'sort_order' => 7,
                'created_at' => now()
            ],
            [
                'code' => 'team',
                'steam_category' => 'TournamentTeam',
                'is_primary' => false,
                'sort_order' => 8,
                'created_at' => now()
            ],
            [
                'code' => 'sticker_category',
                'steam_category' => 'StickerCategory',
                'is_primary' => false,
                'sort_order' => 9,
                'created_at' => now()
            ],
            [
                'code' => 'sticker_capsule',
                'steam_category' => 'StickerCapsule',
                'is_primary' => false,
                'sort_order' => 10,
                'created_at' => now()
            ]
        ]);
    }
};
