<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',          // ID of the user
        'letter_id',        // ID of the letter being tracked
        'level_id',         // ID of the level
        'current_position', // Current position in the level's pattern
        'status',           // Correct or incorrect
        'time_taken',       // Time taken to respond (optional)
    ];

    /**
     * Relationship with User.
     * Progress belongs to a user.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship with AmharicLetter.
     * Progress belongs to a specific letter.
     */
    public function letter()
    {
        return $this->belongsTo(AmharicLetter::class, 'letter_id');
    }

    /**
     * Relationship with Level.
     * Progress belongs to a specific level.
     */
    public function level()
    {
        return $this->belongsTo(Level::class, 'level_id');
    }
}
