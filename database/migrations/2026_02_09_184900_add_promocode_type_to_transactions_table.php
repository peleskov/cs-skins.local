<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE transactions MODIFY COLUMN type ENUM(
            'deposit', 'withdrawal', 'purchase', 'sale', 'fee', 'refund',
            'auction_bid', 'auction_refund', 'case_purchase', 'virtual_item_sale', 'upgrade_bet', 'promocode'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE transactions MODIFY COLUMN type ENUM(
            'deposit', 'withdrawal', 'purchase', 'sale', 'fee', 'refund',
            'auction_bid', 'auction_refund', 'case_purchase', 'virtual_item_sale', 'upgrade_bet'
        ) NOT NULL");
    }
};
