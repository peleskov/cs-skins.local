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
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->string('group')->index();
            $table->string('key')->index();
            $table->string('locale', 10)->index(); // en, ru, de, fr, etc
            $table->text('value')->nullable();
            $table->timestamps();

            // Уникальный индекс для комбинации группы, ключа и языка
            $table->unique(['group', 'key', 'locale']);

            // Дополнительный составной индекс для быстрых запросов
            $table->index(['group', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
