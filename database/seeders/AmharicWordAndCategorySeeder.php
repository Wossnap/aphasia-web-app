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
                        'audio_path' => 'temesgen.ogg',
                        'gif_path' => 'family/temesgen.gif',
                        'show_in_random' => true,
                        'image_path' => null
                    ],
                    [
                        'word' => 'ሙሉ',
                        'transliterations' => ['mule', 'mulu', 'ሙሉ'],
                        'audio_path' => 'mulu.ogg',
                        'levels' => [1],
                        'gif_path' => 'family/mulu.gif',
                        'show_in_random' => true,
                        'image_path' => null
                    ],
                    [
                        'word' => 'ወሰን',
                        'transliterations' => ['ወሰን', 'wesen', 'ዋስን', 'ዋሳን', 'ወሰ', 'ወስነ'],
                        'audio_path' => 'wesen.ogg',
                        'levels' => [1],
                        'gif_path' => 'family/wesen.gif',
                        'show_in_random' => true,
                        'image_path' => null
                    ],
                    [
                        'word' => 'ሚሚ',
                        'transliterations' => ['ሚሚ', 'mimi'],
                        'audio_path' => 'mimi.ogg',
                        'levels' => [1],
                        'gif_path' => 'family/mimi.gif',
                        'show_in_random' => true,
                        'image_path' => null
                    ],
                    [
                        'word' => 'መሰሉ',
                        'transliterations' => ['መሰሉ', 'meselu', 'መሠሩ', 'መሰሩ', 'መሠሉ'],
                        'audio_path' => 'meselu.ogg',
                        'levels' => [1],
                        'gif_path' => 'family/meselu.gif',
                        'show_in_random' => true,
                        'image_path' => null
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
                        'levels' => [1],
                        'gif_path' => 'useful/water.gif',
                        'show_in_random' => true,
                        'image_path' => null
                    ],
                    [
                        'word' => 'ምሳ',
                        'transliterations' => ['ምሳ', 'mesa', 'ሚሳ', 'መሳ', 'ንስሐ', 'ንስሃ', 'ንስሀ'],
                        'audio_path' => 'mesa.ogg',
                        'levels' => [1],
                        'gif_path' => 'useful/lunch.gif',
                        'show_in_random' => true,
                        'image_path' => null
                    ],
                    [
                        'word' => 'ዳቦ',
                        'transliterations' => ['ዳቦ', 'dabo'],
                        'audio_path' => 'dabo.ogg',
                        'levels' => [1],
                        'gif_path' => 'useful/bread.gif',
                        'show_in_random' => true,
                        'image_path' => null
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
                        'levels' => [1],
                        'gif_path' => 'greetings/hello.gif',
                        'show_in_random' => false,
                        'image_path' => null
                    ],
                ]
            ],
            [
                'name' => 'Numbers',
                'description' => 'Numbers',
                'words' => [
                    [
                        'word' => 'አንድ - 1',
                        'transliterations' => ['አንድ', 'ዓንድ', 'ዐንድ', 'ኣንድ', 'and', '1'],
                        'audio_path' => 'and.ogg',
                        'levels' => [1],
                        'gif_path' => 'numbers/and.gif',
                        'show_in_random' => false,
                        'image_path' => 'numbers/and.jpg'
                    ],
                    [
                        'word' => 'ሁለት - 2',
                        'transliterations' => ['ሁለት', 'ኁለት', 'ሑለት', 'hulet', '2'],
                        'audio_path' => 'hulet.ogg',
                        'levels' => [1],
                        'gif_path' => 'numbers/hulet.gif',
                        'show_in_random' => false,
                        'image_path' => 'numbers/hulet.jpg'
                    ],
                    [
                        'word' => 'ሶስት - 3',
                        'transliterations' => ['ሶስት', 'ሶሥት', 'ሦስት', 'ሦሥት', 'sost', '3'],
                        'audio_path' => 'sost.ogg',
                        'levels' => [1],
                        'gif_path' => 'numbers/sost.gif',
                        'show_in_random' => false,
                        'image_path' => 'numbers/sost.jpg'
                    ],
                    [
                        'word' => 'አራት - 4',
                        'transliterations' => ['አራት', 'ዓራት', 'ኣራት', 'ዐራት', 'arat', '4'],
                        'audio_path' => 'arat.ogg',
                        'levels' => [1],
                        'gif_path' => 'numbers/arat.gif',
                        'show_in_random' => false,
                        'image_path' => 'numbers/arat.jpg'
                    ],
                    [
                        'word' => 'አምስት - 5',
                        'transliterations' => ['አምስት', 'ዓምስት', 'ኣምስት', 'ዐምስት', 'amest', '5'],
                        'audio_path' => 'amest.ogg',
                        'levels' => [1],
                        'gif_path' => 'numbers/amest.gif',
                        'show_in_random' => false,
                        'image_path' => 'numbers/amest.jpg'
                    ],
                    [
                        'word' => 'ስድስት - 6',
                        'transliterations' => ['ስድስት', 'sidist', '6'],
                        'audio_path' => 'sidist.ogg',
                        'levels' => [1],
                        'gif_path' => 'numbers/sidist.gif',
                        'show_in_random' => false,
                        'image_path' => 'numbers/sidist.jpg'
                    ],
                    [
                        'word' => 'ሰባት - 7',
                        'transliterations' => ['ሰባት', 'ሠባት', 'ሳባት', 'ሣባት', 'sebat', '7'],
                        'audio_path' => 'sebat.ogg',
                        'levels' => [1],
                        'gif_path' => 'numbers/sebat.gif',
                        'show_in_random' => false,
                        'image_path' => 'numbers/sebat.jpg'
                    ],
                    [
                        'word' => 'ስምንት - 8',
                        'transliterations' => ['ስምንት', 'smnt', '8'],
                        'audio_path' => 'smnt.ogg',
                        'levels' => [1],
                        'gif_path' => 'numbers/smnt.gif',
                        'show_in_random' => false,
                        'image_path' => 'numbers/smnt.jpg'
                    ],
                    [
                        'word' => 'ዘጠኝ - 9',
                        'transliterations' => ['ዘጠኝ', 'zetegn', '9'],
                        'audio_path' => 'zetegn.ogg',
                        'levels' => [1],
                        'gif_path' => 'numbers/zetegn.gif',
                        'show_in_random' => false,
                        'image_path' => 'numbers/zetegn.jpg'
                    ],
                    [
                        'word' => 'አስር - 10',
                        'transliterations' => ['አስር', 'ዓስር', 'ኣስር', 'ዐስር', 'asir', '10'],
                        'audio_path' => 'asir.ogg',
                        'levels' => [1],
                        'gif_path' => 'numbers/asir.gif',
                        'show_in_random' => false,
                        'image_path' => 'numbers/asir.jpg'
                    ]
                ]
            ]
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
                        'audio_path' => $wordData['audio_path'],
                        'gif_path' => $wordData['gif_path'] ?? null,
                        'show_in_random' => $wordData['show_in_random'] ?? true,
                        'image_path' => $wordData['image_path'] ?? null
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