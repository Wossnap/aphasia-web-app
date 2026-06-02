<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Google\Auth\Credentials\ServiceAccountCredentials;

class SpeechTranscriptionService
{
    /**
     * Transcribe audio using Google Cloud Speech-to-Text.
     * Dispatches to v1 (API key) or v2 (Chirp + service account) based on config.
     *
     * @param string $audioBase64
     * @return string|null
     */
    public function transcribe(string $audioBase64): ?string
    {
        return config('services.google_speech.version') === 'v2'
            ? $this->transcribeV2($audioBase64)
            : $this->transcribeV1($audioBase64);
    }

    /**
     * v1 REST API — authenticated with a simple API key.
     */
    protected function transcribeV1(string $audioBase64): ?string
    {
        $apiKey = config('services.google_speech.key');

        if (empty($apiKey)) {
            Log::error('Google Speech API key is missing.');
            return null;
        }

        $url = "https://speech.googleapis.com/v1/speech:recognize?key={$apiKey}";

        $payload = [
            'config' => [
                'encoding' => 'WEBM_OPUS',
                'sampleRateHertz' => 48000,
                'languageCode' => 'am-ET',
                'enableAutomaticPunctuation' => true,
            ],
            'audio' => [
                'content' => $audioBase64,
            ],
        ];

        try {
            Log::info('Google Speech v1 request: ' . $url);

            $response = Http::post($url, $payload);

            Log::info('Google Speech v1 response: ' . $response->body());

            if ($response->successful()) {
                return $this->extractTranscript($response->json());
            }

            Log::error('Google Speech v1 error: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('Google Speech v1 request failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * v2 REST API — Chirp model, authenticated with a service-account bearer token.
     */
    protected function transcribeV2(string $audioBase64): ?string
    {
        $projectId = config('services.google_speech.project_id');
        $location  = config('services.google_speech.location');
        $model     = config('services.google_speech.model');

        if (empty($projectId)) {
            Log::error('GOOGLE_CLOUD_PROJECT_ID is missing for Speech v2.');
            return null;
        }

        $token = $this->accessToken();
        if (empty($token)) {
            Log::error('Could not obtain a Google access token for Speech v2.');
            return null;
        }

        // Chirp requires a regional endpoint host, e.g. us-central1-speech.googleapis.com
        $host = "{$location}-speech.googleapis.com";
        $url  = "https://{$host}/v2/projects/{$projectId}/locations/{$location}/recognizers/_:recognize";

        $payload = [
            'config' => [
                'autoDecodingConfig' => (object) [],
                'model'              => $model,
                'languageCodes'      => ['am-ET'],
                'features'           => [
                    'enableAutomaticPunctuation' => true,
                ],
            ],
            'content' => $audioBase64,
        ];

        try {
            Log::info('Google Speech v2 request: ' . $url);

            $response = Http::withToken($token)->post($url, $payload);

            Log::info('Google Speech v2 response: ' . $response->body());

            if ($response->successful()) {
                return $this->extractTranscript($response->json());
            }

            Log::error('Google Speech v2 error: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('Google Speech v2 request failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetch (and cache) an OAuth access token from the service-account key.
     */
    protected function accessToken(): ?string
    {
        $keyFile = config('services.google_speech.credentials');

        if (empty($keyFile) || !file_exists($keyFile)) {
            Log::error("Service-account key file not found: {$keyFile}");
            return null;
        }

        // Tokens last 1h; cache for 50m to stay safely fresh.
        return Cache::remember('google_speech_v2_token', now()->addMinutes(50), function () use ($keyFile) {
            $creds = new ServiceAccountCredentials(
                'https://www.googleapis.com/auth/cloud-platform',
                $keyFile
            );
            $token = $creds->fetchAuthToken();
            return $token['access_token'] ?? null;
        });
    }

    /**
     * Pull the top transcript out of a v1- or v2-shaped response.
     */
    protected function extractTranscript(?array $data): ?string
    {
        if (isset($data['results']) && count($data['results']) > 0) {
            $alternatives = $data['results'][0]['alternatives'] ?? [];
            if (count($alternatives) > 0) {
                return $alternatives[0]['transcript'] ?? null;
            }
        }

        return null;
    }
}
