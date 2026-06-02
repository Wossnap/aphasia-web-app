<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SpeechTranscriptionService;

class SpeechTest extends Command
{
    protected $signature = 'speech:test {file : Path to an audio file (webm/ogg/wav)}';

    protected $description = 'Transcribe a local audio file through the configured Google Speech driver (v1 or v2).';

    public function handle(SpeechTranscriptionService $service): int
    {
        $path = $this->argument('file');

        if (!file_exists($path)) {
            $this->error("File not found: {$path}");
            return self::FAILURE;
        }

        $this->info('Driver:  ' . config('services.google_speech.driver'));
        $this->info('Version: ' . config('services.google_speech.version'));
        if (config('services.google_speech.version') === 'v2') {
            $this->info('Project: ' . config('services.google_speech.project_id'));
            $this->info('Location: ' . config('services.google_speech.location'));
            $this->info('Model:   ' . config('services.google_speech.model'));
            $this->info('Creds:   ' . config('services.google_speech.credentials'));
        }
        $this->line('');

        $base64 = base64_encode(file_get_contents($path));

        $this->line('Transcribing ' . $path . ' ...');
        $transcript = $service->transcribe($base64);

        if ($transcript === null) {
            $this->error('No transcript returned. Check storage/logs/laravel.log for the API response.');
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Transcript: ' . $transcript);
        return self::SUCCESS;
    }
}
