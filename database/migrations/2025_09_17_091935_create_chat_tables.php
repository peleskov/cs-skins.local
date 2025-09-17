<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Таблица сообщений чата
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->text('message');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['created_at']);
            $table->index(['client_id']);
        });

        // Таблица банов в чате
        Schema::create('chat_bans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->timestamp('banned_until')->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('banned_by')->nullable()->constrained('clients')->onDelete('set null');
            $table->timestamps();

            $table->index(['client_id']);
            $table->index(['banned_until']);
        });

        // Таблица запрещенных слов
        Schema::create('banned_words', function (Blueprint $table) {
            $table->id();
            $table->string('word', 100)->unique();
            $table->timestamps();
        });

        // Вместо триггера будем использовать периодическую очистку через команду
    }

    public function down()
    {
        Schema::dropIfExists('banned_words');
        Schema::dropIfExists('chat_bans');
        Schema::dropIfExists('chat_messages');
    }
};