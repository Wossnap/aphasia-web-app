<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Services\SpeechTranscriptionService;

class SpeechCompareRemote extends Command
{
    protected $signature = 'speech:compare-remote
        {file : JSONL file with {url, word, trans} per line}
        {--csv= : Optional path to also write results as CSV}';

    protected $description = 'Download each audio URL and score v1 vs v2 against its transliteration accept-list.';

    public function handle(SpeechTranscriptionService $svc): int
    {
        $file = $this->argument('file');
        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        $cacheDir = storage_path('app/speech_test/cache');
        @mkdir($cacheDir, 0755, true);

        $lines = array_filter(array_map('trim', file($file)));
        $v1pass = 0; $v2pass = 0; $total = 0; $rows = [];

        $this->line(sprintf("%-28s | %-12s %-3s | %-12s %-3s", 'EXPECTED', 'v1', 'OK', 'v2', 'OK'));
        $this->line(str_repeat('-', 70));

        foreach ($lines as $line) {
            $rec = json_decode($line, true);
            if (!$rec || empty($rec['url'])) {
                continue;
            }

            $ext = pathinfo(parse_url($rec['url'], PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'bin';
            $local = $cacheDir . '/' . md5($rec['url']) . '.' . $ext;
            if (!file_exists($local)) {
                // URL-encode the filename segment (Amharic chars, spaces, parens).
                $base = substr($rec['url'], 0, strrpos($rec['url'], '/') + 1);
                $name = substr($rec['url'], strrpos($rec['url'], '/') + 1);
                $encoded = $base . rawurlencode($name);
                try {
                    $resp = Http::timeout(30)->get($encoded);
                    if (!$resp->ok()) {
                        $this->line(sprintf("%-28s | DOWNLOAD FAILED (%d)", mb_strimwidth($rec['word'], 0, 28), $resp->status()));
                        continue;
                    }
                    file_put_contents($local, $resp->body());
                } catch (\Throwable $e) {
                    $this->line(sprintf("%-28s | DOWNLOAD ERROR: %s", mb_strimwidth($rec['word'], 0, 28), $e->getMessage()));
                    continue;
                }
            }

            $b64 = base64_encode(file_get_contents($local));

            config(['services.google_speech.version' => 'v1']);
            $v1 = $svc->transcribe($b64);
            config(['services.google_speech.version' => 'v2']);
            $v2 = $svc->transcribe($b64);

            $o1 = $this->matches($v1, $rec['trans']);
            $o2 = $this->matches($v2, $rec['trans']);
            $v1pass += $o1 ? 1 : 0;
            $v2pass += $o2 ? 1 : 0;
            $total++;

            $this->line(sprintf(
                "%-28s | %-12s %-3s | %-12s %-3s",
                mb_strimwidth($rec['word'], 0, 28),
                $v1 ?? '∅', $o1 ? 'OK' : 'x',
                $v2 ?? '∅', $o2 ? 'OK' : 'x'
            ));

            $rows[] = [$rec['word'], $v1, $o1 ? 1 : 0, $v2, $o2 ? 1 : 0];
        }

        $this->line(str_repeat('-', 70));
        if ($total > 0) {
            $this->info(sprintf(
                'PASS RATE:  v1 = %d/%d (%.0f%%)   v2 = %d/%d (%.0f%%)',
                $v1pass, $total, 100 * $v1pass / $total,
                $v2pass, $total, 100 * $v2pass / $total
            ));
        }

        if ($csv = $this->option('csv')) {
            $fh = fopen($csv, 'w');
            fputcsv($fh, ['expected', 'v1', 'v1_ok', 'v2', 'v2_ok']);
            foreach ($rows as $r) {
                fputcsv($fh, $r);
            }
            fclose($fh);
            $this->info("CSV written to {$csv}");
        }

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
