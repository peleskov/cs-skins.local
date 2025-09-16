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
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value');
            $table->string('type')->default('string'); // string, number, boolean, json
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index('key');
        });

        // Добавляем начальные настройки комиссий
        $settings = [
            [
                'key' => 'marketplace_fee_percent',
                'value' => '5',
                'type' => 'number',
                'description' => 'Комиссия маркетплейса P2P (%)'
            ],
            [
                'key' => 'auction_fee_percent',
                'value' => '5',
                'type' => 'number',
                'description' => 'Комиссия аукционов (% с продавца)'
            ],
            [
                'key' => 'bot_purchase_fee_percent',
                'value' => '0',
                'type' => 'number',
                'description' => 'Комиссия при быстрой продаже боту (%)'
            ],
            [
                'key' => 'transaction_hold_days',
                'value' => '7',
                'type' => 'number',
                'description' => 'Количество дней холда для транзакций продавца'
            ]
        ];

        foreach ($settings as $setting) {
            DB::table('site_settings')->insert(array_merge($setting, [
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};