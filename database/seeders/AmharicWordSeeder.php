<?php

namespace Database\Seeders;

use App\Models\AmharicWord;
use Illuminate\Database\Seeder;

class AmharicWordSeeder extends Seeder
{
    public function run()
    {
        $words = [
            // [
            //     'word' => 'እንደምን አደርክ',
            //     'transliteration' => 'endemen aderke',
            //     'meaning' => 'How are you (male)',
            //     'audio_path' => 'audio/greetings/how-are-you-m.mp3'
            // ],
            [
                'word' => 'ሰላም',
                'transliterations' => [
                    'selam', 'salam', 'salaam', 'ሰላም', 'ሠላም',
                    'selaam', 'slaam', 'slam', 'seelam', 'salem'
                ],
                'meaning' => 'Hello/Peace',
                'audio_path' => 'selam.ogg'
            ],
            [
                'word' => 'አበበ',
                'transliterations' => ['abebe', 'abeba','አበበ'],
                'audio_path' => 'abebe.ogg'

            ],
            [
                'word' => 'ተመስገን',
                'transliterations' => ['temesgen', 'temmesgen', 'ተመስገን', 'ተመሥገን'],
                'audio_path' => 'temesgen.ogg'
            ],
            [
                'word' => 'ሙሉ',
                'transliterations' => ['mule', 'mulu', 'ሙሉ'],
                'audio_path' => 'mulu.ogg'
            ],
            // Add more words as needed
        ];

        foreach ($words as $word) {
            AmharicWord::updateOrCreate(
                ['word' => $word['word']],
                $word
            );
        }
    }
}
