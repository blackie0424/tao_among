<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Fish;

class FishController extends Controller
{
    //
    public function index()
    {
        $fishes = Fish::all();
        return view('welcome',['fishes'=>$fishes]);
    }
}
