<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SpeechAttempt;
use Illuminate\Http\Request;

class SpeechAttemptController extends Controller
{
    public function index()
    {
        $attempts = SpeechAttempt::with(['user', 'word'])
            ->latest()
            ->paginate(50);

        return view('admin.attempts.index', compact('attempts'));
    }

    public function addTransliteration(SpeechAttempt $attempt)
    {
        $word = $attempt->word;
        $transcription = trim($attempt->transcription);

        if (!$word) {
            return back()->with('error', 'Associated word not found.');
        }

        if (empty($transcription)) {
            return back()->with('error', 'Transcription is empty.');
        }

        $transliterations = $word->transliterations ?? [];

        // Add to transliterations array if not already present
        if (!in_array($transcription, $transliterations)) {
            $transliterations[] = $transcription;
            $word->transliterations = $transliterations;
            $word->save();
        }

        // Mark attempt as correct
        $attempt->is_correct = true;
        $attempt->save();

        return back()->with('success', sprintf(
            'Added "%s" as a valid transliteration for "%s".',
            $transcription,
            $word->word
        ));
    }

    public function destroy(SpeechAttempt $attempt)
    {
        if ($attempt->audio_path) {
            $fullPath = public_path('audio/attempts/' . $attempt->audio_path);
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }

        $attempt->delete();

        return back()->with('success', 'Speech attempt deleted successfully.');
    }
}
