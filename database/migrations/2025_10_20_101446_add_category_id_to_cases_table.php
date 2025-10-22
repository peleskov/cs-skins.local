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
        Schema::table('cases', function (Blueprint $table) {
            // Проверяем, есть ли уже колонка category_id
            if (!Schema::hasColumn('cases', 'category_id')) {
                // Добавляем новую колонку
                $table->unsignedBigInteger('category_id')->nullable();
            } else {
                // Если колонка есть, изменяем её на nullable
                $table->unsignedBigInteger('category_id')->nullable()->change();
            }
        });

        // Обнуляем все существующие значения category_id
        DB::table('cases')->update(['category_id' => null]);

        Schema::table('cases', function (Blueprint $table) {
            // Затем добавляем внешний ключ
            $table->foreign('category_id')->references('id')->on('case_categories')->onDelete('set null');
            $table->index('category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            // Удаляем внешний ключ и индекс, если они существуют
            if (Schema::hasColumn('cases', 'category_id')) {
                $table->dropForeign(['category_id']);
                $table->dropIndex(['category_id']);
                $table->dropColumn('category_id');
            }
        });
    }
};
