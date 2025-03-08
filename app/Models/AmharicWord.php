<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmharicWord extends Model
{
    protected $fillable = [
        'word',
        'transliterations',
        'meaning',
        'audio_path'
    ];

    protected $casts = [
        'transliterations' => 'array'
    ];
}
