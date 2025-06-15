<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FishSize;

class FishSizeController extends Controller
{
    public function show($fish_id)
    {
        $fishSize = \App\Models\FishSize::where('fish_id', $fish_id)->first();

        if (!$fishSize) {
            return response()->json([
                'status' => 'error',
                'message' => 'Not Found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => '取得成功',
            'data' => [
                'fish_id' => $fishSize->fish_id,
                'parts' => $fishSize->parts,
            ],
        ]);
    }
}
