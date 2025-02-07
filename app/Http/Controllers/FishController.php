<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateFishRequest;
use App\Models\Fish;

class FishController extends Controller
{
    //
    public function index()
    {
        $fishes = Fish::all();

        return view('welcome', ['fishes' => $fishes]);
    }

    public function getFish($id)
    {
        $fish = Fish::find($id);

        return view('fish', ['fish' => $fish]);
    }

    public function getFishs()
    {
        $fishes = Fish::all();
        if ($fishes->isEmpty()) {
            return response()->json(['message' => '沒有資料']);
        }

        $assetUrl = env('ASSET_URL', 'https://example.com/images/');
        foreach ($fishes as $fish) {
            if ($fishes->isEmpty() || $fish->image == null) {
                $fish->image = $assetUrl.'/images/default.png';
            } else {
                $fish->image = $assetUrl.'/images/'.$fish->image;
            }
        }

        return response()->json($fishes);
    }

    public function getFishById($id)
    {
        if ($id == null || $id == '' || $id == 'index') {
            return response()->json(['message' => '沒有資料']);
        }
        $fish = Fish::find($id);
        if ($fish == null) {
            return response()->json(['message' => '沒有資料']);
        }
        $assetUrl = env('ASSET_URL', 'https://example.com/images/');
        if ($fish->image == null || $fish->image == '') {
            $fish->image = $assetUrl.'/images/default.png';
        } else {
            $fish->image = $assetUrl.'/images/'.$fish->image;
        }

        return response()->json($fish);
    }

    public function create(CreateFishRequest $request)
    {
        $fish = Fish::create($request->validated());

        return response()->json(['message' => 'fish created successfully', 'data' => $fish], 201);
    }

    public function uploadImage()
    {
        $request = request();
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $imagePath = $request->file('image')->store('images', 'public');

        return response()->json(['message' => 'image uploaded successfully', 'data' => $imagePath], 201);
    }
}
