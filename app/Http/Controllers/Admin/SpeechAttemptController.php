<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SpeechAttempt;
use Illuminate\Http\Request;

class SpeechAttemptController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status'); // 'correct' | 'incorrect' | null (all)
        $from = $request->query('from');     // Y-m-d
        $to = $request->query('to');         // Y-m-d

        $attempts = SpeechAttempt::with(['user', 'word'])
            ->when($status === 'correct', fn ($q) => $q->where('is_correct', true))
            ->when($status === 'incorrect', fn ($q) => $q->where('is_correct', false))
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('admin.attempts.index', compact('attempts', 'status', 'from', 'to'));
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
