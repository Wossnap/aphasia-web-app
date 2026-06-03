<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use App\Models\AmharicWord;
use App\Models\Category;
use Wossnap\AmharicTransliteration\Facades\AmharicTransliteration;

/**
 * Seeds the Amharic fidel (syllabary) as practice words.
 *
 * - One category "ፊደል (Fidel)"; each consonant family is a level (1..34).
 * - Audio is downloaded from amharicteacher.com's CDN into public/audio/letters.
 * - transliterations get homophone variants so matching is forgiving.
 * - engine is left null -> uses the global .env default (v2, best for letters).
 *
 * Idempotent and NON-destructive. Run standalone, NOT via plain db:seed:
 *   php artisan db:seed --class=FidelLettersSeeder
 */
class FidelLettersSeeder extends Seeder
{
    private const CDN = 'https://d9seco0wfq8yu.cloudfront.net/dict/sounds/mp3/';

    public function run(): void
    {
        // Each family: 7 [char, audio-src] pairs in vowel order 1..7.
        $families = [
            ['ሀ','ha'],['ሁ','hu'],['ሂ','hee'],['ሃ','ha'],['ሄ','hae'],['ህ','heh'],['ሆ','ho'],
            ['ለ','le'],['ሉ','lu'],['ሊ','lee'],['ላ','la'],['ሌ','lay'],['ል','leh'],['ሎ','lo'],
            ['ሐ','ha'],['ሑ','hu'],['ሒ','hee'],['ሓ','ha'],['ሔ','hae'],['ሕ','heh'],['ሖ','ho'],
            ['መ','muh'],['ሙ','moo'],['ሚ','mee'],['ማ','ma'],['ሜ','mae'],['ም','mih'],['ሞ','mo'],
            ['ሠ','seh'],['ሡ','soo'],['ሢ','see'],['ሣ','sa'],['ሤ','sae'],['ሥ','sih'],['ሦ','so'],
            ['ረ','reh'],['ሩ','roo'],['ሪ','ree'],['ራ','ra'],['ሬ','rae'],['ር','rih'],['ሮ','ro'],
            ['ሰ','seh'],['ሱ','soo'],['ሲ','see'],['ሳ','sa'],['ሴ','sae'],['ስ','sih'],['ሶ','so'],
            ['ሸ','sheh'],['ሹ','shoo'],['ሺ','shee'],['ሻ','sha'],['ሼ','shae'],['ሽ','shih'],['ሾ','sho'],
            ['ቀ','qeh'],['ቁ','qoo'],['ቂ','qee'],['ቃ','qa'],['ቄ','qae'],['ቅ','qih'],['ቆ','qo'],
            ['በ','beh'],['ቡ','boo'],['ቢ','bee'],['ባ','ba'],['ቤ','bae'],['ብ','bih'],['ቦ','bo'],
            ['ቨ','veh'],['ቩ','voo'],['ቪ','vee'],['ቫ','va'],['ቬ','vae'],['ቭ','vih'],['ቮ','vo'],
            ['ተ','teh'],['ቱ','too'],['ቲ','tee'],['ታ','ta'],['ቴ','tae'],['ት','tih'],['ቶ','to'],
            ['ቸ','cheh'],['ቹ','choo'],['ቺ','chee'],['ቻ','cha'],['ቼ','chae'],['ች','chih'],['ቾ','cho'],
            ['ኀ','ha'],['ኁ','hu'],['ኂ','hee'],['ኃ','ha'],['ኄ','hae'],['ኅ','heh'],['ኆ','ho'],
            ['ነ','neh'],['ኑ','noo'],['ኒ','nee'],['ና','na'],['ኔ','nae'],['ን','nih'],['ኖ','no'],
            ['ኘ','gneh'],['ኙ','gnoo'],['ኚ','gnee'],['ኛ','gna'],['ኜ','gnae'],['ኝ','gnih'],['ኞ','gno'],
            ['አ','aa'],['ኡ','oo'],['ኢ','ee'],['ኣ','aa'],['ኤ','ae'],['እ','ih'],['ኦ','o'],
            ['ከ','keh'],['ኩ','koo'],['ኪ','kee'],['ካ','ka'],['ኬ','kae'],['ክ','kih'],['ኮ','ko'],
            ['ኸ','huh'],['ኹ','hu'],['ኺ','hee'],['ኻ','ha'],['ኼ','hae'],['ኽ','heh'],['ኾ','ho'],
            ['ወ','weh'],['ዉ','woo'],['ዊ','wee'],['ዋ','wa'],['ዌ','wae'],['ው','wih'],['ዎ','wo'],
            ['ዐ','aa'],['ዑ','oo'],['ዒ','ee'],['ዓ','aa'],['ዔ','ae'],['ዕ','ih'],['ዖ','o'],
            ['ዘ','ze'],['ዙ','zu'],['ዚ','zee'],['ዛ','zaa'],['ዜ','zae'],['ዝ','zih'],['ዞ','zo'],
            ['ዠ','zjeh'],['ዡ','zjoo'],['ዢ','zjee'],['ዣ','zjaa'],['ዤ','zjae'],['ዥ','zjih'],['ዦ','zjo'],
            ['የ','ye'],['ዩ','yu'],['ዪ','yee'],['ያ','yaa'],['ዬ','yae'],['ይ','yih'],['ዮ','yo'],
            ['ደ','duh'],['ዱ','doo'],['ዲ','dee'],['ዳ','daa'],['ዴ','dae'],['ድ','dih'],['ዶ','do'],
            ['ጀ','je'],['ጁ','joo'],['ጂ','jee'],['ጃ','jaa'],['ጄ','jae'],['ጅ','jih'],['ጆ','jo'],
            ['ገ','guh'],['ጉ','goo'],['ጊ','gee'],['ጋ','ga'],['ጌ','gae'],['ግ','gih'],['ጎ','go'],
            ['ጠ','tte'],['ጡ','ttu'],['ጢ','ttee'],['ጣ','ttaa'],['ጤ','ttae'],['ጥ','ttih'],['ጦ','tto'],
            ['ጨ','chhe'],['ጩ','chhoo'],['ጪ','chhee'],['ጫ','chhaa'],['ጬ','chhae'],['ጭ','chhih'],['ጮ','cho'],
            ['ጰ','ppuh'],['ጱ','ppoo'],['ጲ','ppee'],['ጳ','ppaa'],['ጴ','ppae'],['ጵ','ppih'],['ጶ','ppo'],
            ['ጸ','tse'],['ጹ','tsoo'],['ጺ','tsee'],['ጻ','tsaa'],['ጼ','tsae'],['ጽ','tsih'],['ጾ','tso'],
            ['ፀ','tse'],['ፁ','tsoo'],['ፂ','tsee'],['ፃ','tsaa'],['ፄ','tsae'],['ፅ','tsih'],['ፆ','tso'],
            ['ፈ','fuh'],['ፉ','foo'],['ፊ','fee'],['ፋ','faa'],['ፌ','fae'],['ፍ','fih'],['ፎ','fo'],
            ['ፐ','peh'],['ፑ','poo'],['ፒ','pee'],['ፓ','paa'],['ፔ','pae'],['ፕ','pih'],['ፖ','po'],
        ];

        $category = Category::firstOrCreate(['name' => 'ፊደል (Fidel)']);

        // Calibrated v2 transcripts per audio-src (what v2 actually hears for each
        // clip). Generated once by `php artisan letters:calibrate`. Added to each
        // letter's accept-list so a correct pronunciation v2 renders oddly (e.g.
        // ቀ -> ከ) still matches. Committed JSON so cPanel needs no API calls.
        $v2map = [];
        $v2file = database_path('data/fidel_v2.json');
        if (file_exists($v2file)) {
            $v2map = json_decode(file_get_contents($v2file), true) ?: [];
        }

        $audioDir = public_path('audio/letters');
        if (!is_dir($audioDir)) {
            @mkdir($audioDir, 0755, true);
        }

        $downloaded = 0; $skipped = 0; $failed = 0;

        foreach ($families as $i => [$char, $src]) {
            $level = intdiv($i, 7) + 1; // 7 letters per family -> family number

            // Download the audio once per unique src.
            $dest = "{$audioDir}/{$src}.mp3";
            if (file_exists($dest)) {
                $skipped++;
            } else {
                try {
                    $resp = Http::timeout(30)->get(self::CDN . $src . '.mp3');
                    if ($resp->ok()) {
                        file_put_contents($dest, $resp->body());
                        $downloaded++;
                    } else {
                        $failed++;
                        $this->command->warn("download failed ({$resp->status()}): {$src}.mp3");
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    $this->command->warn("download error {$src}.mp3: {$e->getMessage()}");
                }
            }

            // Homophone-aware accept-list.
            $variants = [];
            try {
                $variants = AmharicTransliteration::getAmharicVariants($char);
            } catch (\Throwable $e) {
                // package optional — fall back to just the char
            }
            // What v2 actually transcribes this clip as (calibrated, additive).
            $v2extra = !empty($v2map[$src]) ? [$v2map[$src]] : [];

            $trans = array_values(array_unique(array_merge([$char, $src], $variants, $v2extra)));

            // Upsert the word. NOTE: 'engine' is intentionally omitted so re-runs
            // don't clobber any per-letter engine an admin set later.
            $word = AmharicWord::updateOrCreate(
                ['word' => $char],
                [
                    'transliterations' => $trans,
                    'audio_path'       => "letters/{$src}.mp3",
                    'show_in_random'   => false,
                    // Global running order (1..238) so consecutive mode sequences
                    // deterministically instead of relying on (colliding) created_at.
                    'order'            => $i + 1,
                ]
            );

            // Re-point this word's Fidel-category link to the right family level.
            $word->categories()->detach($category->id);
            $word->categories()->attach($category->id, ['level' => $level]);
        }

        $this->command->info(sprintf(
            'Fidel seeded: %d letters across %d families. Audio: %d downloaded, %d already present, %d failed.',
            count($families), intdiv(count($families), 7), $downloaded, $skipped, $failed
        ));
    }
}
