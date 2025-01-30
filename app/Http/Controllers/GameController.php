<?php

namespace App\Http\Controllers;

use App\Models\AmharicLetter;
use App\Models\Level;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function index()
    {
        $level = Level::where('name', 'Level 1')->first();

        $letters = AmharicLetter::where('group_id', 1)->get();

        $currentLetterIndex = 1;

        return view('game.game', compact('level', 'letters', 'currentLetterIndex'));
    }
}
