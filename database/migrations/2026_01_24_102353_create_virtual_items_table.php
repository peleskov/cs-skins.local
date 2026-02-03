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
        Schema::create('virtual_items', function (Blueprint $table) {
            $table->id();
            $table->string('market_hash_name')->unique();
            $table->string('name');
            $table->string('weapon_type')->nullable()->index();
            $table->string('skin_name')->nullable();
            $table->string('quality')->nullable()->index(); // Factory New, Minimal Wear, etc.
            $table->string('rarity')->nullable()->index(); // Consumer Grade, Mil-Spec, Covert, etc.
            $table->string('rarity_color')->nullable(); // Hex color for rarity
            $table->string('image_url')->nullable();
            $table->decimal('price', 15, 2)->default(0)->index();
            $table->decimal('steam_price', 15, 2)->nullable(); // Price from Steam
            $table->boolean('is_stattrak')->default(false)->index();
            $table->boolean('is_souvenir')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('virtual_items');
    }
};
