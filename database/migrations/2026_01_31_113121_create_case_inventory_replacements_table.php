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
        Schema::create('case_inventory_replacements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_inventory_item_id')->constrained('case_inventory_items')->onDelete('cascade');
            $table->foreignId('listing_id')->nullable()->constrained('listings')->onDelete('set null');
            $table->foreignId('trade_offer_id')->nullable()->constrained('trade_offers')->onDelete('set null');
            $table->decimal('original_price', 15, 2);
            $table->decimal('replacement_price', 15, 2)->nullable();
            $table->string('status')->default('pending'); // pending, completed, failed
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_inventory_replacements');
    }
};
