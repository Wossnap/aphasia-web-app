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
                        'image_path' => 'family/temesgen_image.jpg'
                    ],
                    [
                        'word' => 'ሙሉ',
                        'transliterations' => ['mule', 'mulu', 'ሙሉ'],
                        'audio_path' => 'mulu.ogg',
                        'levels' => [1],
                        'gif_path' => 'family/mulu.gif',
                        'show_in_random' => true,
                        'image_path' => 'family/mulu_image.jpg'
                    ],
                    [
                        'word' => 'ወሰን',
                        'transliterations' => ['ወሰን', 'wesen', 'ዋስን', 'ዋሳን', 'ወሰ', 'ወስነ'],
                        'audio_path' => 'wesen.ogg',
                        'levels' => [1],
                        'gif_path' => 'family/wesen.gif'
                    ],
                    [
                        'word' => 'ሚሚ',
                        'transliterations' => ['ሚሚ', 'mimi'],
                        'audio_path' => 'mimi.ogg',
                        'levels' => [1],
                        'gif_path' => 'family/mimi.gif'
                    ],
                    [
                        'word' => 'መሰሉ',
                        'transliterations' => ['መሰሉ', 'meselu', 'መሠሩ', 'መሰሩ', 'መሠሉ'],
                        'audio_path' => 'meselu.ogg',
                        'levels' => [1],
                        'gif_path' => 'family/meselu.gif'
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
                        'image_path' => 'useful/water_image.jpg'
                    ],
                    [
                        'word' => 'ምሳ',
                        'transliterations' => ['ምሳ', 'mesa', 'ሚሳ', 'መሳ', 'ንስሐ', 'ንስሃ', 'ንስሀ'],
                        'audio_path' => 'mesa.ogg',
                        'levels' => [1],
                        'gif_path' => 'useful/lunch.gif'
                    ],
                    [
                        'word' => 'ዳቦ',
                        'transliterations' => ['ዳቦ', 'dabo'],
                        'audio_path' => 'dabo.ogg',
                        'levels' => [1],
                        'gif_path' => 'useful/bread.gif'
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
                        'image_path' => 'greetings/hello_image.jpg'
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
                        'image_path' => 'numbers/and_image.jpg'
                    ],
                    [
                        'word' => 'ሁለት - 2',
                        'transliterations' => ['ሁለት', 'ኁለት', 'ሑለት', 'hulet', '2'],
                        'audio_path' => 'hulet.ogg',
                        'levels' => [1],
                        'gif_path' => 'numbers/hulet.gif',
                        'show_in_random' => false,
                        'image_path' => 'numbers/hulet_image.jpg'
                    ],
                    [
                        'word' => 'ሶስት - 3',
                        'transliterations' => ['ሶስት', 'ሶሥት', 'ሥስት', 'ሦሥት', 'sost', '3'],
                        'audio_path' => 'sost.ogg',
                        'levels' => [1],
                        'gif_path' => 'numbers/sost.gif',
                        'show_in_random' => false,
                        'image_path' => 'numbers/sost_image.jpg'
                    ],
                    [
                        'word' => 'አራት - 4',
                        'transliterations' => ['አራት', 'ዓራት', 'ኣራት', 'ዐራት', 'arat', '4'],
                        'audio_path' => 'arat.ogg',
                        'levels' => [1],
                        'gif_path' => 'numbers/arat.gif',
                        'show_in_random' => false,
                        'image_path' => 'numbers/arat_image.jpg'
                    ],
                    [
                        'word' => 'አምስት - 5',
                        'transliterations' => ['አምስት', 'ዓምስት', 'ኣምስት', 'ዐምስት', 'amest', '5'],
                        'audio_path' => 'amest.ogg',
                        'levels' => [1],
                        'gif_path' => 'numbers/amest.gif',
                        'show_in_random' => false,
                        'image_path' => 'numbers/amest_image.jpg'
                    ],
                    [
                        'word' => 'ስድስት - 6',
                        'transliterations' => ['ስድስት', 'sidist', '6'],
                        'audio_path' => 'sidist.ogg',
                        'levels' => [1],
                        'gif_path' => 'numbers/sidist.gif',
                        'show_in_random' => false,
                        'image_path' => 'numbers/sidist_image.jpg'
                    ],
                    [
                        'word' => 'ሰባት - 7',
                        'transliterations' => ['ሰባት', 'sebat', '7'],
                        'audio_path' => 'sebat.ogg',
                        'levels' => [1],
                        'gif_path' => 'numbers/sebat.gif',
                        'show_in_random' => false,
                        'image_path' => 'numbers/sebat_image.jpg'
                    ],
                    [
                        'word' => 'ስምንት - 8',
                        'transliterations' => ['ስምንት', 'smnt', '8'],
                        'audio_path' => 'smnt.ogg',
                        'levels' => [1],
                        'gif_path' => 'numbers/smnt.gif',
                        'show_in_random' => false,
                        'image_path' => 'numbers/smnt_image.jpg'
                    ],
                    [
                        'word' => 'ዘጠኝ - 9',
                        'transliterations' => ['ዘጠኝ', 'zetegn', '9'],
                        'audio_path' => 'zetegn.ogg',
                        'levels' => [1],
                        'gif_path' => 'numbers/zetegn.gif',
                        'show_in_random' => false,
                        'image_path' => 'numbers/zetegn_image.jpg'
                    ],
                    [
                        'word' => 'አስር - 10',
                        'transliterations' => ['አስር', 'ዓስር', 'ኣስር', 'ዐስር', 'asir', '10'],
                        'audio_path' => 'asir.ogg',
                        'levels' => [1],
                        'gif_path' => 'numbers/asir.gif',
                        'show_in_random' => false,
                        'image_path' => 'numbers/asir_image.jpg'
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