<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $fillable = ['name', 'description', 'slug'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function booted(): void
    {
        // Keep a URL slug in sync with the name (used for /{slug} routing).
        static::saving(function (Category $category) {
            if (empty($category->slug) && !empty($category->name)) {
                $category->slug = Str::slug($category->name) ?: ('category-' . ($category->id ?? uniqid()));
            }
        });
    }

    public function words()
    {
        return $this->belongsToMany(AmharicWord::class, 'category_word')
                    ->withPivot('level')
                    ->withTimestamps();
    }
}
