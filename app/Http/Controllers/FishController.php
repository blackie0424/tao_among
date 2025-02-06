<?php

namespace App\Http\Controllers;

use App\Models\Fish;
use Illuminate\Http\Request;

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

    public function create(Request $request)
    {
        $data = $request->all();
        if ($data['name'] == null || $data['name'] == '') {
            return response()->json(['message' => 'fish can not created', 'data' => $data], 201);
        }
        if ($data['locate'] == null || $data['locate'] == '') {
            return response()->json(['message' => 'fish can not created', 'data' => $data], 201);
        }
        if ($data['image'] == null || $data['image'] == '') {
            return response()->json(['message' => 'fish can not created', 'data' => $data], 201);
        }
        $fish = new Fish;
        $fish->name = $data['name'];
        $fish->type = $data['type'];
        $fish->locate = $data['locate'];
        $fish->image = $data['image'];
        $fish->save();

        return response()->json(['message' => 'fish created successfully', 'data' => $data], 201);
    }
}
