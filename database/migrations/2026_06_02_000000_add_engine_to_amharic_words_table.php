<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amharic_words', function (Blueprint $table) {
            // null = use the global .env default; otherwise 'v1' or 'v2'.
            $table->string('engine')->nullable()->after('order');
        });
    }

    public function down(): void
    {
        Schema::table('amharic_words', function (Blueprint $table) {
            $table->dropColumn('engine');
        });
    }
};
