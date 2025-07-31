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
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('reserved_until')->nullable()->after('paid_at');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('reserved_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->timestamp('reserved_until')->nullable()->after('completed_at');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('reserved_until');
        });
    }
};