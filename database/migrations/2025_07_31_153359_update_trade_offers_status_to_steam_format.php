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
        // Изменяем тип колонки с enum на varchar для поддержки Steam статусов
        Schema::table('trade_offers', function (Blueprint $table) {
            $table->string('status', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Возвращаем enum (может потребовать ручной настройки в зависимости от БД)
        Schema::table('trade_offers', function (Blueprint $table) {
            $table->enum('status', ['pending', 'dispatched', 'sent', 'completed', 'cancelled'])->change();
        });
    }
};