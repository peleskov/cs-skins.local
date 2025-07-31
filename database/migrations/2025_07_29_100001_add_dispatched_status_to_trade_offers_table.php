<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MySQL требует пересоздания ENUM для добавления нового значения
        DB::statement("ALTER TABLE trade_offers MODIFY COLUMN status ENUM('pending', 'dispatched', 'sent', 'completed', 'cancelled') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Сначала меняем все dispatched на pending
        DB::table('trade_offers')->where('status', 'dispatched')->update(['status' => 'pending']);
        
        // Возвращаем старый ENUM
        DB::statement("ALTER TABLE trade_offers MODIFY COLUMN status ENUM('pending', 'sent', 'completed', 'cancelled') DEFAULT 'pending'");
    }
};