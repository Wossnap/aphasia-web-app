<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmharicWord extends Model
{
    protected $fillable = [
        'word',
        'transliterations',
        'meaning',
        'audio_path',
        'gif_path',
        'show_in_random',
        'image_path'
    ];

    protected $casts = [
        'transliterations' => 'array',
        'show_in_random' => 'boolean'
    ];

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_word')
                    ->withPivot('level')
                    ->withTimestamps();
    }
}
