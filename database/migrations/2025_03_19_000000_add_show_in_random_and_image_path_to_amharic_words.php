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
        Schema::table('amharic_words', function (Blueprint $table) {
            $table->boolean('show_in_random')->default(true)->after('gif_path');
            $table->string('image_path')->nullable()->after('show_in_random');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('amharic_words', function (Blueprint $table) {
            $table->dropColumn(['show_in_random', 'image_path']);
        });
    }
};
