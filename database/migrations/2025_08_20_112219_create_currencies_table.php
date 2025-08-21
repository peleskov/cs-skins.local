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
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Название валюты');
            $table->string('symbol', 10)->comment('Символ валюты');
            $table->string('code', 3)->unique()->comment('Код валюты (USD, RUB, EUR)');
            $table->decimal('exchange_rate', 10, 4)->default(1)->comment('Курс к основной валюте');
            $table->boolean('is_primary')->default(false)->comment('Основная валюта');
            $table->boolean('is_active')->default(true)->comment('Активна ли валюта');
            $table->integer('sort_order')->default(0)->comment('Порядок сортировки');
            $table->timestamps();
            
            $table->index('is_primary');
            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};