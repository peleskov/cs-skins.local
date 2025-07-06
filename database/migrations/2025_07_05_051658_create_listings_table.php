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
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('seller_id')->constrained('clients');
            $table->foreignId('buyer_id')->nullable()->constrained('clients');
            $table->decimal('price', 10, 2);
            $table->enum('status', ['active', 'sold', 'cancelled', 'expired'])->default('active');
            $table->float('wear_value', 8, 6)->nullable();
            $table->integer('pattern_index')->nullable();
            $table->json('stickers')->nullable();
            $table->string('name_tag')->nullable();
            $table->boolean('is_stattrak')->default(false);
            $table->boolean('is_souvenir')->default(false);
            $table->timestamp('listed_at')->useCurrent();
            $table->timestamp('sold_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'listed_at']);
            $table->index(['item_id', 'status']);
            $table->index(['seller_id', 'status']);
            $table->index(['price', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
