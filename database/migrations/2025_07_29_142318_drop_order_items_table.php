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
        Schema::dropIfExists('order_items');
    }

    public function down(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('listing_id')->nullable();
            $table->unsignedBigInteger('seller_id');
            $table->integer('quantity')->default(1);
            $table->enum('status', ['reserved', 'completed', 'cancelled'])->default('reserved');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->unsignedBigInteger('trade_offer_id')->nullable();
            $table->string('item_name');
            $table->string('item_image_url')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('seller_name');
            $table->string('buyer_name');
            $table->timestamps();
            
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('listing_id')->references('id')->on('listings')->onDelete('set null');
            $table->foreign('seller_id')->references('id')->on('clients');
            $table->foreign('trade_offer_id')->references('id')->on('trade_offers')->onDelete('set null');
        });
    }
};
