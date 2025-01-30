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

    public function getFish($id){
        $fish = Fish::find($id);
        return view('fish',['fish'=>$fish]);
    }

    public function getFishs(){
        $fishes = Fish::all();
        if ($fishes->isEmpty()) {
            return response()->json(['message' => '沒有資料']);
        }

        $assetUrl = env('ASSET_URL', 'https://example.com/images/');
        foreach ($fishes as $fish) {
            if ($fishes->isEmpty() || $fish->image == null) {
                $fish->image = $assetUrl ."/images/default.png";
            } else {
                $fish->image = $assetUrl ."/images/".$fish->image;
            }
        }
        return response()->json($fishes);
    }
    
    public function getFishById($id) {
        $fish = Fish::find($id);
        return response()->json($fish);
    }
}
