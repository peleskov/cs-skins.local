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
        Schema::create('upgrades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->json('bet_items');                          // [{item_id, price}, ...]
            $table->decimal('bet_balance', 15, 2)->default(0);  // Добавленный баланс (только основной!)
            $table->decimal('total_bet', 15, 2);                // Общая ставка
            $table->foreignId('target_virtual_item_id')->constrained('virtual_items');
            $table->decimal('target_price', 15, 2);             // Цена целевого предмета
            $table->decimal('win_chance', 5, 2);                // Шанс выигрыша в %
            $table->decimal('roll_value', 5, 2);                // Выпавшее значение (0-100)
            $table->enum('result', ['win', 'lose']);
            $table->foreignId('won_item_id')->nullable()->constrained('case_inventory_items');
            $table->timestamp('created_at')->useCurrent();

            $table->index('client_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upgrades');
    }
};
