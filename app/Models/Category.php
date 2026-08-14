<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $fillable = ['name', 'description', 'slug', 'session_mode', 'easy_level_mode'];

    /**
     * Stated on the model as well as in the column default, so a category
     * built in memory reads the same as one loaded back from the database
     * rather than answering null until it is refreshed.
     */
    protected $attributes = [
        'session_mode' => self::SESSION_BY_WORD,
        'easy_level_mode' => self::EASY_AS_ROWS,
    ];

    /** A guided session works item by item. */
    public const SESSION_BY_WORD = 'word';

    /**
     * A guided session works a level at a time, strongest levels first. What a
     * level means is the category's own business — one consonant family in the
     * fidel category, one difficulty tier in the word categories — and the
     * engine does not need to know which.
     */
    public const SESSION_BY_LEVEL = 'level';

    public static function sessionModes(): array
    {
        return [
            self::SESSION_BY_WORD => 'Word by word — pick items from anywhere in the category',
            self::SESSION_BY_LEVEL => 'Level by level — work through one level at a time, strongest first',
        ];
    }

    public function worksByLevel(): bool
    {
        return $this->session_mode === self::SESSION_BY_LEVEL;
    }

    /** Easy levels are walked as whole rows, like any other level. */
    public const EASY_AS_ROWS = 'rows';

    /**
     * Easy levels are broken up and spent as short runs of wins between the
     * harder rows. Blocked practice for what he is learning, mixed practice
     * for what he already has — which is where each has its evidence, though
     * the aphasia evidence for the mixed half is one small study.
     */
    public const EASY_AS_MIXED = 'mixed';

    public static function easyLevelModes(): array
    {
        return [
            self::EASY_AS_ROWS => 'Whole rows — easy levels are walked like any other level',
            self::EASY_AS_MIXED => 'Mixed in as wins — easy letters are spread between the harder rows',
        ];
    }

    public function mixesEasyLevels(): bool
    {
        return $this->worksByLevel() && $this->easy_level_mode === self::EASY_AS_MIXED;
    }

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
