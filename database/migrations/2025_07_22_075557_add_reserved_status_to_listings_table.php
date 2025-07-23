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
        Schema::table('listings', function (Blueprint $table) {
            // Изменяем ENUM поле status, добавляя reserved
            $table->enum('status', ['pending', 'active', 'reserved', 'sold', 'cancelled', 'expired'])->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            // Возвращаем обратно без reserved
            $table->enum('status', ['pending', 'active', 'sold', 'cancelled', 'expired'])->default('pending')->change();
        });
    }
};
