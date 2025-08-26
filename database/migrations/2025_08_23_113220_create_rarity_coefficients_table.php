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
        Schema::create('rarity_coefficients', function (Blueprint $table) {
            $table->id();
            $table->string('steam_name')->unique(); // consumer, industrial, milspec и т.д.
            $table->string('display_name_ru'); // Ширпотреб, Промышленное, Армейское и т.д.
            $table->string('display_name_en')->nullable(); // Consumer Grade, Industrial Grade и т.д.
            $table->decimal('coefficient', 3, 2); // 0.50, 0.40, 0.20 и т.д.
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('steam_name');
            $table->index('is_active');
        });

        // Добавляем начальные данные
        $coefficients = [
            ['steam_name' => 'consumer', 'display_name_ru' => 'Ширпотреб', 'display_name_en' => 'Consumer Grade', 'coefficient' => 0.50, 'sort_order' => 1],
            ['steam_name' => 'industrial', 'display_name_ru' => 'Промышленное качество', 'display_name_en' => 'Industrial Grade', 'coefficient' => 0.50, 'sort_order' => 2],
            ['steam_name' => 'milspec', 'display_name_ru' => 'Армейское качество', 'display_name_en' => 'Mil-Spec Grade', 'coefficient' => 0.40, 'sort_order' => 3],
            ['steam_name' => 'restricted', 'display_name_ru' => 'Запрещенное', 'display_name_en' => 'Restricted', 'coefficient' => 0.35, 'sort_order' => 4],
            ['steam_name' => 'classified', 'display_name_ru' => 'Засекреченное', 'display_name_en' => 'Classified', 'coefficient' => 0.30, 'sort_order' => 5],
            ['steam_name' => 'covert', 'display_name_ru' => 'Тайное', 'display_name_en' => 'Covert', 'coefficient' => 0.20, 'sort_order' => 6],
            ['steam_name' => 'contraband', 'display_name_ru' => 'Контрабанда', 'display_name_en' => 'Contraband', 'coefficient' => 0.20, 'sort_order' => 7],
        ];

        foreach ($coefficients as $coefficient) {
            DB::table('rarity_coefficients')->insert(array_merge($coefficient, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rarity_coefficients');
    }
};