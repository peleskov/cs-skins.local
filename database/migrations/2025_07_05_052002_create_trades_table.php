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
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings');
            $table->foreignId('buyer_id')->constrained('clients');
            $table->foreignId('seller_id')->constrained('clients');
            $table->decimal('price', 10, 2);
            $table->decimal('fee_amount', 10, 2)->default(0);
            $table->enum('status', ['pending', 'completed', 'cancelled', 'expired'])->default('pending');
            $table->enum('type', ['instant', 'p2p'])->default('p2p');
            $table->string('trade_offer_id')->nullable();
            $table->timestamp('initiated_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('trade_data')->nullable();
            $table->timestamps();

            $table->index(['status', 'initiated_at']);
            $table->index(['buyer_id', 'status']);
            $table->index(['seller_id', 'status']);
            $table->index('trade_offer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
