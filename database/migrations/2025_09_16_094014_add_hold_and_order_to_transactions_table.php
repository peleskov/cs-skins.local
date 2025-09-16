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
        Schema::table('transactions', function (Blueprint $table) {
            // Добавляем поле для холда
            $table->timestamp('hold_until')->nullable()->after('status');

            // Добавляем связь с заказом
            $table->foreignId('order_id')->nullable()->after('client_id')
                ->constrained('orders')->onDelete('cascade');

            // Удаляем trade_id если есть (не используется)
            if (Schema::hasColumn('transactions', 'trade_id')) {
                $table->dropColumn('trade_id');
            }

            // Индексы для оптимизации
            $table->index(['status', 'hold_until']);
            $table->index(['order_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['status', 'hold_until']);
            $table->dropIndex(['order_id', 'type']);
            $table->dropForeign(['order_id']);
            $table->dropColumn(['hold_until', 'order_id']);
        });
    }
};