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
        Schema::create('case_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->string('name'); // Название уровня (Супер, Крутой, etc)
            $table->decimal('price', 10, 2); // Цена уровня
            $table->decimal('probability', 5, 2); // Вероятность выпадения в %
            $table->timestamps();

            $table->index(['case_id', 'price']); // Индекс для сортировки по цене
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_tiers');
    }
};
