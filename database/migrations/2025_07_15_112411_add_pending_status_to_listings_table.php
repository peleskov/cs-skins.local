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
            // Изменяем ENUM поле status, добавляя pending
            $table->enum('status', ['pending', 'active', 'sold', 'cancelled', 'expired'])->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            // Возвращаем обратно к исходному ENUM
            $table->enum('status', ['active', 'sold', 'cancelled', 'expired'])->default('active')->change();
        });
    }
};
