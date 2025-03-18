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
            $table->string('gif_path')->nullable()->after('audio_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('amharic_words', function (Blueprint $table) {
            $table->dropColumn('gif_path');
        });
    }
};
