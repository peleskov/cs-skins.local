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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            
            // Steam identifiers
            $table->string('steam_market_hash_name')->unique();
            $table->string('steam_classid')->nullable();
            $table->string('steam_instanceid')->nullable();
            
            // Names
            $table->string('name_ru');
            $table->string('name_en');
            
            // Categories
            $table->enum('type', [
                'knife', 'pistol', 'rifle', 'smg', 'shotgun', 'machinegun', 'sniper',
                'gloves', 'sticker', 'graffiti', 'case', 'key', 'music_kit', 'agent', 'pass'
            ]);
            $table->string('weapon')->nullable(); // AK-47, AWP, etc
            
            // Rarity
            $table->enum('rarity', [
                'consumer', 'industrial', 'mil_spec', 'restricted', 'classified', 'covert', 'contraband'
            ]);
            
            // Image
            $table->string('image_url');
            
            // Pricing
            $table->decimal('min_steam_price', 10, 2)->nullable();
            $table->integer('steam_listings_count')->default(0);
            $table->boolean('is_valid')->default(false); // Valid for bot purchase (>200 listings)
            
            // Bot purchase coefficients (from TZ)
            $table->decimal('buyout_coefficient', 3, 2)->nullable(); // 0.20 - 0.50
            
            // Additional info
            $table->text('description_ru')->nullable();
            $table->text('description_en')->nullable();
            $table->json('tags')->nullable(); // StatTrak, Souvenir, etc
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['type', 'rarity']);
            $table->index(['is_valid', 'min_steam_price']);
            $table->index('steam_listings_count');
            $table->fullText(['name_ru', 'name_en']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
