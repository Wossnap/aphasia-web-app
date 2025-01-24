<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmharicLetter extends Model
{
    use HasFactory;

    protected $fillable = [
        'letter',             // The individual letter
        'group_id',           // The ID of the group this letter belongs to
        'position',           // Position in the group
        'transliterations',   // JSON field for transliterations
    ];

    protected $casts = [
        'transliterations' => 'array', // Automatically cast JSON to array
    ];

    /**
     * Relationship with AmharicLetterGroup.
     * A letter belongs to one group.
     */
    public function group()
    {
        return $this->belongsTo(AmharicLetterGroup::class, 'group_id');
    }
}
