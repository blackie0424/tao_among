<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FishSize;

class FishSizeController extends Controller
{
    public function show($fish_id)
    {
        $fishSize = FishSize::where('fish_id', $fish_id)->first();

        if (!$fishSize) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return response()->json([
            'fish_id' => $fishSize->fish_id,
            'parts' => $fishSize->parts,
        ]);
    }
}
