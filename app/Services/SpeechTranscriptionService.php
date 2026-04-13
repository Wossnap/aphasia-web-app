<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SpeechTranscriptionService
{
    /**
     * Transcribe audio using Google Cloud Speech-to-Text REST API.
     *
     * @param string $audioBase64
     * @return string|null
     */
    public function transcribe(string $audioBase64): ?string
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

            Log::info('Google Speech API request: ' . $url);
            // Log::info('Google Speech API payload: ' . json_encode($payload));

            $response = Http::post($url, $payload);

            Log::info('Google Speech API response: ' . $response->body());

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['results']) && count($data['results']) > 0) {
                    $alternatives = $data['results'][0]['alternatives'] ?? [];
                    if (count($alternatives) > 0) {
                        return $alternatives[0]['transcript'] ?? null;
                    }
                }
                
                return null;
            }

            Log::error('Google Speech API error: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('Google Speech API request failed: ' . $e->getMessage());
            return null;
        }
    }
}
