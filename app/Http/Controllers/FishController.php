<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateFishRequest;
use App\Models\Fish;
use App\Services\FishService;
use App\Services\SupabaseStorageService;

class FishController extends Controller
{
    protected $fishService;

    public function __construct(FishService $fishService)
    {
        $this->fishService = $fishService;
    }

    public function index(): View
    {
        return view('welcome', ['fishes' => $this->fishService->getAllFishes()]);
    }

    public function getFish($id): View
    {
        return view('fish', ['fish' => $this->fishService->getFishById($id)]);
    }

    public function getFishs()
    {
        $fishes = Fish::all();
        if ($fishes->isEmpty()) {
            return response()->json(['message' => 'No data available', 'data' => []]);
        }
        if (env('APP_ENV') == 'local' || env('APP_ENV') == 'testing') {
            $assetUrl = env('ASSET_URL');
            foreach ($fishes as $fish) {
                if ($fishes->isEmpty() || $fish->image == null) {
                    $fish->image = $assetUrl.'/images/default.png';
                } else {
                    $fish->image = $assetUrl.'/images/'.$fish->image;
                }
            }
        } else {
            foreach ($fishes as $fish) {
                $storageService = new SupabaseStorageService;
                if ($fishes->isEmpty() || $fish->image == null) {
                    $fish->image = $storageService->getUrl('default.png');
                } else {
                    $fish->image = $storageService->getUrl($fish->image);
                }
            }
        }

        return response()->json(['message' => 'success', 'data' => $fishes]);
    }

    public function getFishById($id)
    {

        $fish = Fish::find($id);
        if (empty($fish)) {
            return response()->json(['message' => 'data not found']);
        }
        if (env('APP_ENV') == 'local' || env('APP_ENV') == 'testing') {
            $assetUrl = env('ASSET_URL');
            if ($fish->image == null || $fish->image == '') {
                $fish->image = $assetUrl.'/images/default.png';
            } else {
                $fish->image = $assetUrl.'/images/'.$fish->image;
            }
        } else {
            $storageService = new SupabaseStorageService;
            if ($fish->image == null || $fish->image == '') {
                $fish->image = $storageService->getUrl('default.png');
            } else {
                $fish->image = $storageService->getUrl($fish->image);
            }
        }

        return response()->json(['message' => 'success', 'data' => $fish]);
    }

    public function create(CreateFishRequest $request)
    {
        try {
            $fish = Fish::create($request->validated());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'fish created failed', 'data' => $e->errors()], 400);
        }

        return response()->json(['message' => 'fish created successfully', 'data' => $fish], 201);
    }
}
