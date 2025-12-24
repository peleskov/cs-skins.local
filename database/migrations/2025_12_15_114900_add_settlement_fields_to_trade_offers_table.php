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
        Schema::table('trade_offers', function (Blueprint $table) {
            $table->boolean('delay_settlement')->default(false)->after('status');
            $table->timestamp('settlement_date')->nullable()->after('delay_settlement');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trade_offers', function (Blueprint $table) {
            $table->dropColumn(['delay_settlement', 'settlement_date']);
        });
    }
};
