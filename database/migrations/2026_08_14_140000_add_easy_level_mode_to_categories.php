<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How the easy levels are used in a level-by-level session.
 *
 * 'rows' walks them like any other level, a whole family at a time. 'mixed'
 * breaks them up and spends their letters as short runs of wins between the
 * harder rows.
 *
 * There is a real research question behind this and no settled answer. The
 * one aphasia study to test blocked against random practice found the two
 * equal for learning something, and random slightly better for still having
 * it three months later — in 4 of 10 people, by an amount the authors
 * themselves called modest. That is not enough to choose on his behalf, so
 * it is a setting, and his own data can answer it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('easy_level_mode', 16)->default('rows')->after('session_mode');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('easy_level_mode');
        });
    }
};
