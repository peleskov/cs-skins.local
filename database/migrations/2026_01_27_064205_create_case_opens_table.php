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
        Schema::create('case_opens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('case_inventory_item_id')->constrained('case_inventory_items')->cascadeOnDelete();
            $table->decimal('price_paid', 15, 2);
            $table->decimal('balance_used', 15, 2)->default(0);
            $table->decimal('bonus_balance_used', 15, 2)->default(0);
            $table->boolean('is_free')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at', 'idx_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_opens');
    }
};
