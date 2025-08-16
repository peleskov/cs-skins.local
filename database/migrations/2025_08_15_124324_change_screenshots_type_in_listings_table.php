<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Сначала очищаем существующие JSON данные и устанавливаем null
        DB::table('listings')->update(['screenshots' => null]);
        
        Schema::table('listings', function (Blueprint $table) {
            // Изменяем тип поля screenshots с JSON на BOOLEAN
            $table->boolean('screenshots')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            // Возвращаем обратно к JSON
            $table->json('screenshots')->nullable()->change();
        });
    }
};