<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SpeechTranscriptionService;

class LettersCalibrate extends Command
{
    protected $signature = 'letters:calibrate';

    protected $description = 'Run v2 on every downloaded fidel clip and save src->transcript to database/data/fidel_v2.json';

    public function handle(SpeechTranscriptionService $svc): int
    {
        $dir = public_path('audio/letters');
        if (!is_dir($dir)) {
            $this->error("No letters audio at {$dir} — run the seeder first.");
            return self::FAILURE;
        }

        $cache = storage_path('app/letter_test/cache');
        @mkdir($cache, 0755, true);

        $mp3s = glob("{$dir}/*.mp3");
        sort($mp3s);
        $map = [];

        config(['services.google_speech.version' => 'v2']);

        foreach ($mp3s as $idx => $mp3) {
            $src  = pathinfo($mp3, PATHINFO_FILENAME);
            $webm = "{$cache}/conv_{$src}.webm";

            if (!file_exists($webm)) {
                $cmd = sprintf('ffmpeg -y -i %s -ar 48000 -ac 1 -c:a libopus %s 2>/dev/null',
                    escapeshellarg($mp3), escapeshellarg($webm));
                exec($cmd, $o, $ret);
                if ($ret !== 0 || !file_exists($webm)) {
                    $this->warn("ffmpeg failed: {$src}");
                    continue;
                }
            }

            $v2 = $svc->transcribe(base64_encode(file_get_contents($webm)));
            $map[$src] = $v2;
            $this->line(sprintf('%-8s -> %s', $src, $v2 ?? '∅'));

            if (($idx + 1) % 25 === 0) {
                $this->comment('  ... ' . ($idx + 1) . '/' . count($mp3s));
            }
        }

        $outDir = database_path('data');
        @mkdir($outDir, 0755, true);
        $out = "{$outDir}/fidel_v2.json";
        file_put_contents($out, json_encode($map, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $this->info('Wrote ' . count($map) . " results to {$out}");
        return self::SUCCESS;
    }
}
