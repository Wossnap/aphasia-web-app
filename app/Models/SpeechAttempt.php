<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpeechAttempt extends Model
{
    protected $fillable = [
        'user_id',
        'amharic_word_id',
        'transcription',
        'checked_transliterations',
        'audio_path',
        'is_correct',
    ];

    protected $casts = [
        'checked_transliterations' => 'array',
        'is_correct'               => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function word(): BelongsTo
    {
        return $this->belongsTo(AmharicWord::class, 'amharic_word_id');
    }

    /**
     * Recent-session accuracy for one user on one word.
     *
     * A "session" is one calendar day of attempts. Mirrors standard speech-
     * therapy practice: track percent accuracy per session and call a word
     * "mastered" only once accuracy holds at 80%+ across 2 consecutive
     * sessions, rather than off a single attempt or a flat attempt count.
     * Returns null when the word has never been attempted (nothing to show).
     */
    public static function progressFor(int $userId, int $wordId): ?array
    {
        $sessionWindow    = (int) config('services.word_progress.session_window', 5);
        $masteryThreshold = (float) config('services.word_progress.mastery_threshold', 0.8);
        $masterySessions  = (int) config('services.word_progress.mastery_sessions', 2);
        $improvingScore   = (int) config('services.word_progress.improving_score', 5);

        $attempts = static::where('user_id', $userId)
            ->where('amharic_word_id', $wordId)
            ->orderByDesc('created_at')
            ->get(['is_correct', 'created_at']);

        if ($attempts->isEmpty()) {
            return null;
        }

        // groupBy preserves first-seen order, and attempts are already newest
        // first, so the most recent calendar day is always the first group.
        $sessions = $attempts
            ->groupBy(fn ($a) => $a->created_at->toDateString())
            ->map(function ($day) {
                $total = $day->count();
                $correct = $day->where('is_correct', true)->count();
                return ['attempts' => $total, 'correct' => $correct, 'accuracy' => $correct / $total];
            })
            ->take($sessionWindow)
            ->values();

        $recentAttempts = $sessions->sum('attempts');
        $recentCorrect = $sessions->sum('correct');
        $accuracy = $recentAttempts > 0 ? $recentCorrect / $recentAttempts : 0;
        $score = (int) round($accuracy * 10);

        $mastered = $sessions->count() >= $masterySessions
            && $sessions->take($masterySessions)->every(fn ($s) => $s['accuracy'] >= $masteryThreshold);

        $trend = null;
        if ($sessions->count() >= 2) {
            $trend = $sessions[0]['accuracy'] <=> $sessions[1]['accuracy'];
            $trend = $trend === 1 ? 'up' : ($trend === -1 ? 'down' : 'flat');
        }

        return [
            'attempts' => $recentAttempts,
            'correct' => $recentCorrect,
            'accuracy' => (int) round($accuracy * 100),
            'score' => $score,
            'status' => $mastered ? 'mastered' : ($score >= $improvingScore ? 'improving' : 'needs_practice'),
            'trend' => $trend,
            'last_attempt_at' => $attempts->first()->created_at,
        ];
    }
}
