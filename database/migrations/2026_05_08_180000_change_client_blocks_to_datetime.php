<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // timestamp ограничен 2038 годом — для бессрочных блокировок нужен datetime
        DB::statement('ALTER TABLE clients MODIFY withdraw_blocked_until DATETIME NULL');
        DB::statement('ALTER TABLE clients MODIFY purchases_blocked_until DATETIME NULL');
        DB::statement('ALTER TABLE clients MODIFY balance_blocked_until DATETIME NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE clients MODIFY withdraw_blocked_until TIMESTAMP NULL');
        DB::statement('ALTER TABLE clients MODIFY purchases_blocked_until TIMESTAMP NULL');
        DB::statement('ALTER TABLE clients MODIFY balance_blocked_until TIMESTAMP NULL');
    }
};
