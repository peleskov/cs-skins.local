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
            // Сначала изменяем колонку на nullable
            $table->bigInteger('category_id')->unsigned()->nullable()->change();
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
            $table->dropForeign(['category_id']);
            $table->dropIndex(['category_id']);
            // Не удаляем колонку, так как она может быть нужна
            // $table->dropColumn('category_id');
        });
    }
};
