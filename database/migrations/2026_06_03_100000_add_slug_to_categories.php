<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        // Backfill slugs from name; fall back to category-{id}; ensure uniqueness.
        $used = [];
        foreach (DB::table('categories')->get(['id', 'name']) as $cat) {
            $base = Str::slug($cat->name) ?: ('category-' . $cat->id);
            $slug = $base;
            $n = 2;
            while (in_array($slug, $used, true)) {
                $slug = $base . '-' . $n++;
            }
            $used[] = $slug;
            DB::table('categories')->where('id', $cat->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
