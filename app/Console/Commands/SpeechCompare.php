<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AmharicWord;
use App\Services\SpeechTranscriptionService;

class SpeechCompare extends Command
{
    protected $signature = 'speech:compare';

    protected $description = 'Run v1 and v2 over every word with audio and score against the real matching logic.';

    public function handle(SpeechTranscriptionService $svc): int
    {
        $words = AmharicWord::whereNotNull('audio_path')
            ->where('audio_path', '!=', '')
            ->get();

        $v1pass = 0; $v2pass = 0; $total = 0;

        $this->line(sprintf("%-11s | %-9s %-2s | %-9s %-2s", 'FILE', 'v1', 'OK', 'v2', 'OK'));
        $this->line(str_repeat('-', 44));

        foreach ($words as $w) {
            $path = public_path('audio/' . $w->audio_path);
            if (!file_exists($path)) {
                continue;
            }
            $b64 = base64_encode(file_get_contents($path));

            config(['services.google_speech.version' => 'v1']);
            $v1 = $svc->transcribe($b64);
            config(['services.google_speech.version' => 'v2']);
            $v2 = $svc->transcribe($b64);

            $o1 = $this->matches($v1, $w->transliterations);
            $o2 = $this->matches($v2, $w->transliterations);
            $v1pass += $o1 ? 1 : 0;
            $v2pass += $o2 ? 1 : 0;
            $total++;

            $this->line(sprintf(
                "%-11s | %-9s %-2s | %-9s %-2s",
                $w->audio_path,
                $v1 ?? '∅', $o1 ? 'OK' : 'x',
                $v2 ?? '∅', $o2 ? 'OK' : 'x'
            ));
        }

        $this->line(str_repeat('-', 44));
        $this->info(sprintf('PASS RATE:  v1 = %d/%d   v2 = %d/%d', $v1pass, $total, $v2pass, $total));

        return self::SUCCESS;
    }

    /** Mirror of the Practice.vue match: spoken.includes(t) for any transliteration. */
    private function matches(?string $spoken, $trans): bool
    {
        if (!$spoken) {
            return false;
        }
        foreach ((array) $trans as $t) {
            if ($t !== '' && str_contains(mb_strtolower($spoken), mb_strtolower($t))) {
                return true;
            }
        }
        return false;
    }
}
