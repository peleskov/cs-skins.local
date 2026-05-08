<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_secret_links', function (Blueprint $table) {
            $table->id();
            $table->string('token', 32)->unique();
            $table->string('label')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('max_visits')->nullable();
            $table->unsignedInteger('visits_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_secret_links');
    }
};
