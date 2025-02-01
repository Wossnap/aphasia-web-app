<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AmharicLettersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $letters = [
            ['letter' => 'ሀ', 'group_id' => 1, 'position' => 1, 'transliterations' => json_encode(['ሀ', 'ሃ', "ሐ", "ሓ", "ኀ", "ኃ", 'hä', 'ha', 'haa', 'haaa', 'hah', 'huh', 'haaaha', 'haha', 'hahaha']), 'created_at' => now(), 'updated_at' => now()],
            ['letter' => 'ሁ', 'group_id' => 1, 'position' => 2, 'transliterations' => json_encode(['ሁ', "ሑ", "ኁ", 'hu', 'huu', 'who']), 'created_at' => now(), 'updated_at' => now()],
            ['letter' => 'ሂ', 'group_id' => 1, 'position' => 3, 'transliterations' => json_encode(['ሂ', "ሒ", "ኂ", 'hi','hii', 'he']), 'created_at' => now(), 'updated_at' => now()],
            ['letter' => 'ሃ', 'group_id' => 1, 'position' => 4, 'transliterations' => json_encode(['ሀ', 'ሃ', "ሐ", "ሓ", "ኀ", "ኃ", 'hä', 'ha', 'haaa', 'hah', 'huh', 'haaaha']), 'created_at' => now(), 'updated_at' => now()],
            ['letter' => 'ሄ', 'group_id' => 1, 'position' => 5, 'transliterations' => json_encode(['ሄ', "ሔ", "ኄ", 'ሄይ', "ሔይ", "ኄይ", 'he', 'hey','heh', 'hee', 'hate']), 'created_at' => now(), 'updated_at' => now()],
            ['letter' => 'ህ', 'group_id' => 1, 'position' => 6, 'transliterations' => json_encode(['ህ', "ሕ", "ኅ", "ህብ", 'heeeeh', 'eh', 'heh']), 'created_at' => now(), 'updated_at' => now()],
            ['letter' => 'ሆ', 'group_id' => 1, 'position' => 7, 'transliterations' => json_encode(['ሆ', "ሖ", "ኆ", 'ho', 'oh', 'hoo', 'who']), 'created_at' => now(), 'updated_at' => now()],
        ];

        foreach ($letters as $letter) {
            DB::table('amharic_letters')->updateOrInsert(
                ['letter' => $letter['letter']], // Unique field
                $letter
            );
        }
    }
}
