<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a guided session works through a category.
 *
 * 'word' picks items from anywhere in the category. 'level' works a level at a
 * time, strongest levels first — which in the fidel category means one
 * consonant family at a time, and in the word categories means one difficulty
 * tier at a time. Both are useful, they are useful for different categories,
 * and which one suits is a judgement about the person practising rather than
 * something the code should decide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('session_mode', 16)->default('word')->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('session_mode');
        });
    }
};
