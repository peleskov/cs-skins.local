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
            // CSFloat данные
            $table->decimal('float_min', 10, 8)->nullable()->after('float_value')->comment('Минимальный износ для скина');
            $table->decimal('float_max', 10, 8)->nullable()->after('float_min')->comment('Максимальный износ для скина');
            $table->integer('paint_index')->nullable()->after('float_max')->comment('ID раскраски скина');
            $table->integer('def_index')->nullable()->after('paint_index')->comment('ID базового оружия');
            $table->bigInteger('csfloat_id')->nullable()->after('def_index')->comment('Уникальный ID в CSFloat');
            $table->timestamp('float_fetched_at')->nullable()->after('csfloat_id')->comment('Когда получены float данные');
            
            // Добавляем индекс для быстрого поиска по csfloat_id
            $table->index('csfloat_id', 'idx_csfloat_id');
            
            // Индекс для поиска предметов без float данных
            $table->index(['float_value', 'inspect_url'], 'idx_float_pending');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_inventory_items', function (Blueprint $table) {
            $table->dropIndex('idx_csfloat_id');
            $table->dropIndex('idx_float_pending');
            
            $table->dropColumn([
                'float_min',
                'float_max', 
                'paint_index',
                'def_index',
                'csfloat_id',
                'float_fetched_at'
            ]);
        });
    }
};