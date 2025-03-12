<?php

namespace Database\Seeders;

use App\Models\AmharicWord;
use App\Models\Category;
use Illuminate\Database\Seeder;

class AmharicWordAndCategorySeeder extends Seeder
{
    public function run()
    {
        // Clear existing categories and words
        // Instead of truncating, we will delete existing records to avoid foreign key constraint issues
        AmharicWord::query()->delete();
        Category::query()->delete();

        // Define categories
        $categories = [
            [
                'name' => 'Family Names',
                'description' => 'Family names',
                'words' => [
                    [
                        'word' => 'ተመስገን',
                        'transliterations' => ['temesgen', 'temmesgen', 'ተመስገን', 'ተመሥገን'],
                        'levels' => [1],
                        'audio_path' => 'temesgen.ogg'

                    ],
                    [
                        'word' => 'ሙሉ',
                        'transliterations' => ['mule', 'mulu', 'ሙሉ'],
                        'audio_path' => 'mulu.ogg',
                        'levels' => [1]
                    ],
                    [
                        'word' => 'ወሰን',
                        'transliterations' => ['ወሰን', 'wesen'],
                        'audio_path' => 'wesen.ogg',
                        'levels' => [1]
                    ],
                    [
                        'word' => 'ሚሚ',
                        'transliterations' => ['ሚሚ', 'mimi'],
                        'audio_path' => 'mimi.ogg',
                        'levels' => [1]
                    ],
                    [
                        'word' => 'መሰሉ',
                        'transliterations' => ['መሰሉ', 'meselu'],
                        'audio_path' => 'meselu.ogg',
                        'levels' => [1]
                    ],
                ]
            ],
            [
                'name' => 'Useful Words',
                'description' => 'Useful words',
                'words' => [
                    [
                        'word' => 'ዉሃ',
                        'transliterations' => ['wuha', 'ዉሃ', 'ውሀ', 'ውሃ', 'ውሃ', 'ውኃ', 'ውኃ'],
                        'audio_path' => 'wuha.ogg',
                        'levels' => [1]
                    ],
                    [
                        'word' => 'ምሳ',
                        'transliterations' => ['ምሳ', 'mesa', 'ሚሳ', 'መሳ', 'ንስሐ', 'ንስሃ', 'ንስሀ'],
                        'audio_path' => 'mesa.ogg',
                        'levels' => [1]
                    ],
                    [
                        'word' => 'ዳቦ',
                        'transliterations' => ['ዳቦ', 'dabo'],
                        'audio_path' => 'dabo.ogg',
                        'levels' => [1]
                    ],
                ]
            ],
            [
                'name' => 'Greetings',
                'description' => 'Greetings',
                'words' => [
                    [
                        'word' => 'ሰላም',
                        'transliterations' => ['selam', 'salam', 'salaam', 'ሰላም', 'ሠላም'],
                        'audio_path' => 'selam.ogg',
                        'levels' => [1]
                    ],
                ]
            ],
        ];

        foreach ($categories as $categoryData) {
            $category = Category::create([
                'name' => $categoryData['name'],
                'description' => $categoryData['description']
            ]);

            foreach ($categoryData['words'] as $wordData) {
                // Find or create the word
                $word = AmharicWord::firstOrCreate(
                    ['word' => $wordData['word']],
                    [
                        'transliterations' => $wordData['transliterations'],
                        'audio_path' => $wordData['audio_path']
                    ]
                );

                // Attach the word to the category with its levels
                foreach ($wordData['levels'] as $level) {
                    $category->words()->attach($word->id, ['level' => $level]);
                }
            }
        }
    }
}