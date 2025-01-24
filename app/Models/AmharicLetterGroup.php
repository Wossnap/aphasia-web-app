<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmharicLetterGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_letter', // Unique identifier for the group
    ];

    /**
     * Relationship with AmharicLetter.
     * A group can have many letters.
     */
    public function letters()
    {
        return $this->hasMany(AmharicLetter::class, 'group_id');
    }
}
