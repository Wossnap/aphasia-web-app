<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LevelsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $levels = [
            ['name' => 'Level 1', 'pattern' => json_encode(
                [1,2,3,4,5,6,7]), 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Level 2', 'pattern' => json_encode(['repeat', 'sequential']), 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Level 5', 'pattern' => json_encode(['random']), 'created_at' => now(), 'updated_at' => now()],
        ];

        foreach ($levels as $level) {
            DB::table('levels')->updateOrInsert(
                ['name' => $level['name']], // Unique field
                $level
            );
        }
    }
}
