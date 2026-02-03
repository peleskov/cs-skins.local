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
        Schema::table('bonus_transactions', function (Blueprint $table) {
            $table->foreign('promocode_id')->references('id')->on('promocodes')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bonus_transactions', function (Blueprint $table) {
            $table->dropForeign(['promocode_id']);
        });
    }
};
