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
        Schema::table('items', function (Blueprint $table) {
            $table->text('image_fn')->nullable()->after('image_url'); // Factory New
            $table->text('image_mw')->nullable()->after('image_fn'); // Minimal Wear
            $table->text('image_ft')->nullable()->after('image_mw'); // Field-Tested
            $table->text('image_ww')->nullable()->after('image_ft'); // Well-Worn
            $table->text('image_bs')->nullable()->after('image_ww'); // Battle-Scarred
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['image_fn', 'image_mw', 'image_ft', 'image_ww', 'image_bs']);
        });
    }
};
