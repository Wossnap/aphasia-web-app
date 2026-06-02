<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SpeechTranscriptionService;

class SpeechController extends Controller
{
    protected $speechService;

    public function __construct(SpeechTranscriptionService $speechService)
    {
        $this->speechService = $speechService;
    }

    public function transcribe(Request $request)
    {
        // A valid per-word version override implies a Google request, so honor it
        // even when the global default driver is not 'google'.
        $requestedVersion = in_array($request->input('version'), ['v1', 'v2'], true)
            ? $request->input('version')
            : null;

        \Illuminate\Support\Facades\Log::info('transcribe() called', [
            'driver' => config('services.google_speech.driver'),
            'requested_version' => $request->input('version') ?: '(none sent)',
            'effective_version' => $requestedVersion ?? config('services.google_speech.version'),
            'has_audio' => $request->hasFile('audio'),
        ]);

        if (!$requestedVersion && config('services.google_speech.driver') !== 'google') {
            \Illuminate\Support\Facades\Log::warning('Google Speech driver not enabled, returning 403');
            return response()->json(['error' => 'Google Speech driver is not enabled'], 403);
        }

        $request->validate([
            'audio' => 'required|file|mimes:webm,ogg'
        ]);

        // Per-word override wins; otherwise the service falls back to the .env default.
        if ($requestedVersion) {
            config(['services.google_speech.version' => $requestedVersion]);
        }

        $file = $request->file('audio');
        $audioContent = file_get_contents($file->getRealPath());
        $audioBase64 = base64_encode($audioContent);

        $transcript = $this->speechService->transcribe($audioBase64);

        if ($transcript === null) {
            return response()->json([
                'results' => []
            ]);
        }

        // Return a similar structure to the original google API so JS doesn't have to change much, or just a simple string.
        // the original google API returns an array. Let's return what JS needs.
        // wait, JS expects pure string: JSON.parse(response).transcript might be nice.
        return response()->json([
            'results' => [
                [
                    'alternatives' => [
                        [
                            'transcript' => $transcript,
                            'confidence' => 1.0
                        ]
                    ]
                ]
            ]
        ]);
        
    }
}
