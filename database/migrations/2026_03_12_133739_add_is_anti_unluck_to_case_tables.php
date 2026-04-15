<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_opens', function (Blueprint $table) {
            $table->boolean('is_anti_unluck')->default(false)->after('is_free');
        });

        Schema::table('case_inventory_items', function (Blueprint $table) {
            $table->boolean('is_anti_unluck')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('case_opens', function (Blueprint $table) {
            $table->dropColumn('is_anti_unluck');
        });

        Schema::table('case_inventory_items', function (Blueprint $table) {
            $table->dropColumn('is_anti_unluck');
        });
    }
};
