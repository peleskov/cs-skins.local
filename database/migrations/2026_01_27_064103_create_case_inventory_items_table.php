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
        Schema::create('case_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('virtual_item_id')->constrained('virtual_items')->cascadeOnDelete();
            $table->decimal('price', 15, 2);
            $table->enum('source_type', ['case', 'upgrade']);
            $table->unsignedBigInteger('source_id');
            $table->enum('status', ['available', 'withdrawn', 'sold', 'upgraded'])->default('available');
            $table->timestamps();

            $table->index(['client_id', 'status'], 'idx_client_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_inventory_items');
    }
};
