<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Добавляем case_purchase в ENUM поле type
        DB::statement("ALTER TABLE transactions MODIFY COLUMN type ENUM('deposit', 'withdrawal', 'purchase', 'sale', 'fee', 'refund', 'auction_bid', 'auction_refund', 'case_purchase') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Возвращаем ENUM без case_purchase
        DB::statement("ALTER TABLE transactions MODIFY COLUMN type ENUM('deposit', 'withdrawal', 'purchase', 'sale', 'fee', 'refund', 'auction_bid', 'auction_refund') NOT NULL");
    }
};
