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
        Schema::table('cases', function (Blueprint $table) {
            // Тип кейса
            $table->enum('case_type', ['normal', 'free', 'limited'])->default('normal')->after('is_active');

            // Для бесплатных кейсов
            $table->decimal('free_min_deposit', 10, 2)->nullable()->after('case_type');
            $table->unsignedInteger('free_opens_count')->nullable()->after('free_min_deposit');

            // Для временных/лимитированных кейсов
            $table->datetime('available_until')->nullable()->after('free_opens_count');
            $table->unsignedInteger('total_opens_limit')->nullable()->after('available_until');
            $table->unsignedInteger('total_opens_count')->default(0)->after('total_opens_limit');

            // Метки
            $table->boolean('label_hot')->default(false)->after('total_opens_count');
            $table->boolean('label_new')->default(false)->after('label_hot');
            $table->boolean('label_limited')->default(false)->after('label_new');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn([
                'case_type',
                'free_min_deposit',
                'free_opens_count',
                'available_until',
                'total_opens_limit',
                'total_opens_count',
                'label_hot',
                'label_new',
                'label_limited',
            ]);
        });
    }
};
