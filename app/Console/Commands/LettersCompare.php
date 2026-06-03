<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Services\SpeechTranscriptionService;

class LettersCompare extends Command
{
    protected $signature = 'letters:compare {file : JSONL with {url, char, rom} per line}';

    protected $description = 'Download each fidel mp3, convert to webm/opus, and compare v1 vs v2 recognition.';

    public function handle(SpeechTranscriptionService $svc): int
    {
        $file = $this->argument('file');
        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        $cache = storage_path('app/letter_test/cache');
        @mkdir($cache, 0755, true);

        $lines = array_filter(array_map('trim', file($file)));
        $v1pass = 0; $v2pass = 0; $total = 0;

        $this->line(sprintf("%-6s %-5s | %-8s %-3s | %-8s %-3s", 'CHAR', 'rom', 'v1', 'OK', 'v2', 'OK'));
        $this->line(str_repeat('-', 44));

        foreach ($lines as $line) {
            $rec = json_decode($line, true);
            if (!$rec || empty($rec['url'])) {
                continue;
            }

            $key  = md5($rec['url'] . $rec['char']);
            $mp3  = "{$cache}/{$key}.mp3";
            $webm = "{$cache}/{$key}.webm";

            if (!file_exists($webm)) {
                try {
                    $resp = Http::timeout(30)->get($rec['url']);
                    if (!$resp->ok()) { $this->line("{$rec['char']}  download failed ({$resp->status()})"); continue; }
                    file_put_contents($mp3, $resp->body());
                } catch (\Throwable $e) {
                    $this->line("{$rec['char']}  download error: {$e->getMessage()}");
                    continue;
                }
                // Convert mp3 -> webm/opus 48k mono (matches v1 config + production mic format)
                $cmd = sprintf('ffmpeg -y -i %s -ar 48000 -ac 1 -c:a libopus %s 2>/dev/null',
                    escapeshellarg($mp3), escapeshellarg($webm));
                exec($cmd, $o, $ret);
                if ($ret !== 0 || !file_exists($webm)) { $this->line("{$rec['char']}  ffmpeg failed"); continue; }
            }

            $b64 = base64_encode(file_get_contents($webm));

            config(['services.google_speech.version' => 'v1']);
            $v1 = $svc->transcribe($b64);
            config(['services.google_speech.version' => 'v2']);
            $v2 = $svc->transcribe($b64);

            $o1 = $this->matches($v1, $rec);
            $o2 = $this->matches($v2, $rec);
            $v1pass += $o1 ? 1 : 0;
            $v2pass += $o2 ? 1 : 0;
            $total++;

            $this->line(sprintf(
                "%-6s %-5s | %-8s %-3s | %-8s %-3s",
                $rec['char'], $rec['rom'],
                $v1 ?? '∅', $o1 ? 'OK' : 'x',
                $v2 ?? '∅', $o2 ? 'OK' : 'x'
            ));
        }

        $this->line(str_repeat('-', 44));
        if ($total > 0) {
            $this->info(sprintf(
                'PASS RATE:  v1 = %d/%d (%.0f%%)   v2 = %d/%d (%.0f%%)',
                $v1pass, $total, 100 * $v1pass / $total,
                $v2pass, $total, 100 * $v2pass / $total
            ));
        }

        return self::SUCCESS;
    }

    /** A letter matches if the transcript contains the exact fidel char. */
    private function matches(?string $spoken, array $rec): bool
    {
        if (!$spoken) {
            return false;
        }
        return str_contains($spoken, $rec['char']);
    }
}
