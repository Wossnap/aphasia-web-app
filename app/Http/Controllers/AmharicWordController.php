<?php

namespace App\Http\Controllers;

use App\Models\AmharicWord;
use Illuminate\Http\Request;

class AmharicWordController extends Controller
{
    public function getRandomWord()
    {
        return AmharicWord::inRandomOrder()->first();
    }

    public function practice()
    {
        return view('practice.amharic');
    }
}
