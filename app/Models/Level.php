<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Level extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',    // Level name
        'pattern', // JSON field for patterns
    ];

    protected $casts = [
        'pattern' => 'array', // Automatically cast JSON to array
    ];

    /**
     * Relationship with UserProgress.
     * A level can have many user progress entries.
     */
    public function userProgress()
    {
        return $this->hasMany(UserProgress::class, 'level_id');
    }
}
