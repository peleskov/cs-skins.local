<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('rigging_enabled')->default(false)->after('balance_block_reason_user');
            $table->timestamp('rigging_until')->nullable()->after('rigging_enabled');
        });

        Schema::create('client_rigging_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price_percent', 6, 2);   // % от цены кейса
            $table->decimal('chance_percent', 6, 2);  // % шанса
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_rigging_presets');
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['rigging_enabled', 'rigging_until']);
        });
    }
};
