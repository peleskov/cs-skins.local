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
        Schema::create('trade_offer_status_history', function (Blueprint $table) {
            $table->foreignId('trade_offer_id')->constrained()->onDelete('cascade');
            $table->string('status');
            $table->timestamp('created_at')->useCurrent();
            
            $table->primary(['trade_offer_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trade_offer_status_history');
    }
};
