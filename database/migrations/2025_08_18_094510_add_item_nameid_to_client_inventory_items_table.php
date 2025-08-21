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
        Schema::table('client_inventory_items', function (Blueprint $table) {
            $table->bigInteger('item_nameid')->nullable()->after('market_hash_name');
            $table->timestamp('item_nameid_fetched_at')->nullable()->after('item_nameid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_inventory_items', function (Blueprint $table) {
            $table->dropColumn(['item_nameid', 'item_nameid_fetched_at']);
        });
    }
};
