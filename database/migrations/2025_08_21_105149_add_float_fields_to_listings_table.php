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
            // Добавляем float поля из CSFloat API
            $table->decimal('float_min', 10, 8)->nullable()->after('float_value')->comment('Минимальный износ для скина');
            $table->decimal('float_max', 10, 8)->nullable()->after('float_min')->comment('Максимальный износ для скина');
            $table->integer('paint_index')->nullable()->after('float_max')->comment('ID раскраски скина');
            $table->integer('def_index')->nullable()->after('paint_index')->comment('ID базового оружия');
            $table->bigInteger('csfloat_id')->nullable()->after('def_index')->comment('Уникальный ID в CSFloat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn([
                'float_min',
                'float_max',
                'paint_index', 
                'def_index',
                'csfloat_id'
            ]);
        });
    }
};