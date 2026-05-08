<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Удаляем старый булев флаг — заменяем на datetime-механику
            $table->dropColumn('withdraw_blocked');
        });

        Schema::table('clients', function (Blueprint $table) {
            // 6.5: три отдельные блокировки. Активна когда _until > now()
            $table->timestamp('withdraw_blocked_until')->nullable()->after('admin_comment');
            $table->text('withdraw_block_reason_admin')->nullable()->after('withdraw_blocked_until');
            $table->text('withdraw_block_reason_user')->nullable()->after('withdraw_block_reason_admin');

            $table->timestamp('purchases_blocked_until')->nullable()->after('withdraw_block_reason_user');
            $table->text('purchases_block_reason_admin')->nullable()->after('purchases_blocked_until');
            $table->text('purchases_block_reason_user')->nullable()->after('purchases_block_reason_admin');

            $table->timestamp('balance_blocked_until')->nullable()->after('purchases_block_reason_user');
            $table->text('balance_block_reason_admin')->nullable()->after('balance_blocked_until');
            $table->text('balance_block_reason_user')->nullable()->after('balance_block_reason_admin');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'withdraw_blocked_until', 'withdraw_block_reason_admin', 'withdraw_block_reason_user',
                'purchases_blocked_until', 'purchases_block_reason_admin', 'purchases_block_reason_user',
                'balance_blocked_until', 'balance_block_reason_admin', 'balance_block_reason_user',
            ]);
            $table->boolean('withdraw_blocked')->default(false);
        });
    }
};
