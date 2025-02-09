<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateFishRequest;
use App\Models\Fish;
use App\Services\FishService;
use App\Services\SupabaseStorageService;
use Illuminate\Http\JsonResponse;

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

    public function getFishs(): JsonResponse
    {
        $fishes = $this->fishService->getAllFishes();

        return response()->json([
            'message' => $fishes->isNotEmpty() ? 'success' : 'No data available',
            'data' => $fishes->isNotEmpty() ? $fishes : [],
        ]);
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
