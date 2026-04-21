<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promocodes', function (Blueprint $table) {
            $table->foreignId('partner_id')->nullable()->after('is_active')
                ->constrained('partners')->nullOnDelete();
            $table->unsignedInteger('lr_offer_id')->nullable()->after('partner_id');
        });
    }

    public function down(): void
    {
        Schema::table('promocodes', function (Blueprint $table) {
            $table->dropForeign(['partner_id']);
            $table->dropColumn(['partner_id', 'lr_offer_id']);
        });
    }
};
