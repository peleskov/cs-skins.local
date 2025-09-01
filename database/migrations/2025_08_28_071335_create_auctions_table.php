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
        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('clients');
            $table->foreignId('listing_id')->constrained('listings');
            $table->decimal('starting_price', 10, 2);
            $table->decimal('current_price', 10, 2);
            $table->integer('bid_count')->default(0);
            $table->foreignId('last_bidder_id')->nullable()->constrained('clients');
            $table->decimal('min_bid_increment', 10, 2)->default(10.00);
            $table->enum('status', ['pending', 'active', 'completed', 'cancelled'])->default('pending');
            $table->datetime('starts_at')->nullable();
            $table->datetime('ends_at')->nullable();
            $table->boolean('auto_extend')->default(false);
            $table->integer('duration_hours')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'ends_at']);
            $table->index('current_price');
            $table->index('bid_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auctions');
    }
};