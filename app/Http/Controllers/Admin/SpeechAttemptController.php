<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetDisplayTimezone;
use App\Models\SpeechAttempt;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class SpeechAttemptController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status'); // 'correct' | 'incorrect' | null (all)
        $from = $request->query('from');     // Y-m-d
        $to = $request->query('to');         // Y-m-d

        // The picker means "this day where the viewer is", but the column is
        // stored in the application zone — comparing the two directly would
        // include or drop the hours either side of local midnight.
        $fromAt = $this->dayBoundary($from, 'start');
        $toAt = $this->dayBoundary($to, 'end');

        $attempts = SpeechAttempt::with(['user', 'word'])
            ->when($status === 'correct', fn ($q) => $q->where('is_correct', true))
            ->when($status === 'incorrect', fn ($q) => $q->where('is_correct', false))
            ->when($fromAt, fn ($q) => $q->where('created_at', '>=', $fromAt))
            ->when($toAt, fn ($q) => $q->where('created_at', '<=', $toAt))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('admin.attempts.index', compact('attempts', 'status', 'from', 'to'));
    }

    /**
     * A Y-m-d from the date picker, read as the start or end of that day in
     * the viewer's zone and handed back in the application's for comparison
     * against storage. Null for anything unparseable, which simply leaves
     * that side of the filter off rather than erroring.
     */
    private function dayBoundary(?string $date, string $edge): ?CarbonImmutable
    {
        if (!$date) {
            return null;
        }

        try {
            $at = CarbonImmutable::createFromFormat('Y-m-d', $date, SetDisplayTimezone::current());
        } catch (\Throwable) {
            return null;
        }

        return ($edge === 'start' ? $at->startOfDay() : $at->endOfDay())
            ->setTimezone(config('app.timezone'));
    }

    public function addTransliteration(Request $request, SpeechAttempt $attempt)
    {
        $word = $attempt->word;
        $transcription = trim($attempt->transcription);

        if (!$word) {
            return $this->addTransliterationResponse($request, false, 'Associated word not found.');
        }

        if (empty($transcription)) {
            return $this->addTransliterationResponse($request, false, 'Transcription is empty.');
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

        return $this->addTransliterationResponse($request, true, sprintf(
            'Added "%s" as a valid transliteration for "%s".',
            $transcription,
            $word->word
        ));
    }

    private function addTransliterationResponse(Request $request, bool $ok, string $message)
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['ok' => $ok, 'message' => $message], $ok ? 200 : 422);
        }

        return back()->with($ok ? 'success' : 'error', $message);
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
