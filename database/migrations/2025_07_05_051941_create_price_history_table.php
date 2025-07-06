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
        Schema::create('price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items');
            $table->decimal('price_min', 10, 2);
            $table->decimal('price_max', 10, 2);
            $table->decimal('price_avg', 10, 2);
            $table->integer('volume')->default(0);
            $table->integer('listings_count')->default(0);
            $table->enum('period', ['hour', 'day', 'week', 'month'])->default('day');
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['item_id', 'period', 'recorded_at']);
            $table->index(['recorded_at']);
            $table->unique(['item_id', 'period', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_history');
    }
};
