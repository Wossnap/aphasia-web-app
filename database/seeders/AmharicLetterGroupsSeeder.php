<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AmharicLetterGroupsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $groups = [
            ['group_letter' => 'ሀ', 'created_at' => now(), 'updated_at' => now()],
            ['group_letter' => 'ለ', 'created_at' => now(), 'updated_at' => now()],
            ['group_letter' => 'መ', 'created_at' => now(), 'updated_at' => now()],
        ];

        foreach ($groups as $group) {
            DB  ::table('amharic_letter_groups')->updateOrInsert(
                ['group_letter' => $group['group_letter']], // Unique field
                $group
            );
        }
    }
}
