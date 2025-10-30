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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('merchant_order_id')->unique();
            $table->string('order_id')->nullable(); // Order ID from ArCoPay
            $table->decimal('amount', 10, 2); // Amount in rubles
            $table->string('currency', 3)->default('RUB');
            $table->text('payment_url')->nullable(); // QR code URL
            $table->enum('status', ['created', 'pending', 'paid', 'failed', 'expired', 'cancelled'])->default('created');
            $table->json('webhook_data')->nullable(); // Store webhook response
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
