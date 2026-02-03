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
        DB::statement("ALTER TABLE payments MODIFY COLUMN payment_type ENUM('sbp', 'card', 'test') NOT NULL DEFAULT 'sbp'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE payments MODIFY COLUMN payment_type ENUM('sbp', 'card') NOT NULL DEFAULT 'sbp'");
    }
};
