<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Проверяем, не существует ли уже системный клиент
        $systemClient = DB::table('clients')
            ->where('email', 'system@cs-skins.local')
            ->first();

        if (!$systemClient) {
            DB::table('clients')->insert([
                'name' => 'Система',
                'email' => 'system@cs-skins.local',
                'steam_id' => 'SYSTEM',
                'steam_avatar' => null,
                'steam_trade_url' => null,
                'balance' => 0,
                'payment_password' => Hash::make('system-' . bin2hex(random_bytes(16))),
                'is_verified' => true,
                'is_bot' => false,
                'locale' => 'ru',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Удаляем системного клиента
        DB::table('clients')
            ->where('email', 'system@cs-skins.local')
            ->delete();
    }
};
