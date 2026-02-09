<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE case_inventory_items MODIFY COLUMN status ENUM('available', 'pending_withdrawal', 'withdrawn', 'sold', 'upgraded') DEFAULT 'available'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE case_inventory_items MODIFY COLUMN status ENUM('available', 'withdrawn', 'sold', 'upgraded') DEFAULT 'available'");
    }
};
