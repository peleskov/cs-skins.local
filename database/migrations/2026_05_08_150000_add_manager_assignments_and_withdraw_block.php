<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Привязка партнёров к менеджеру (для роли partner_manager)
        Schema::create('user_partner', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->unique(['user_id', 'partner_id']);
        });

        // 6.5 Запрет на вывод (стартовый минимум — флаг на клиенте)
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('withdraw_blocked')->default(false)->after('is_bot');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_partner');
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('withdraw_blocked');
        });
    }
};
