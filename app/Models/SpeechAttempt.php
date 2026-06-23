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
}
