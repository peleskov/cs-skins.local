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
            $table->boolean('is_ready')->default(false)->after('status');
            
            // Индекс для быстрого поиска готовых к отправке трейдов
            $table->index(['status', 'is_ready']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trade_offers', function (Blueprint $table) {
            $table->dropIndex(['status', 'is_ready']);
            $table->dropColumn('is_ready');
        });
    }
};