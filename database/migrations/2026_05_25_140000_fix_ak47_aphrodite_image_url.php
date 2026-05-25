<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $correctUrl = 'https://community.akamai.steamstatic.com/economy/image/i0CoZ81Ui0m-9KwlBY1L_18myuGuq1wfhWSaZgMttyVfPaERSR0Wqmu7LAocGIGz3UqlXOLrxM-vMGmW8VNxu5Dx60noTyLwlcK3wiVI7PqRaa9SJPqaB2mvzedxuPUnGCi3wktzt2rRn92pdXuXbA4iDcdxQOIMsBK4k9S2Zeiw4lTdjdhNyTK-0H1wmrL4zA';

        // Битый URL ведёт на несуществующий файл в counter-strike-image-tracker
        // (slug "djiin" вместо реального).
        DB::table('virtual_items')
            ->where('market_hash_name', 'like', 'AK-47 | Aphrodite%')
            ->where('image_url', 'like', '%djiin%')
            ->update(['image_url' => $correctUrl, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Восстанавливать битый URL смысла нет.
    }
};
