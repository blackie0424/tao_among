<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\CreateFishRequest;
use App\Models\Fish;
use App\Services\FishService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

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

    public function getFishs(Request $request): JsonResponse
    {

        $since = $request->query('since');
        if ($since && !is_numeric($since)) {
            return response()->json([
                'message' => 'Invalid since parameter',
                'data' => null,
                'lastUpdateTime' => time()
            ], 400);
        }

        $fishes = $since ? $this->fishService->getFishesBySince($since) : $this->fishService->getAllFishes();

        return response()->json([
            'message' => $fishes->isNotEmpty() ? 'success' : 'No data available',
            'data' => $fishes->isNotEmpty() ? $fishes : [],
            'lastUpdateTime' => time()
        ]);
    }

    public function getFishById($id): JsonResponse
    {
        $fish = $this->fishService->getFishById($id);

        return response()->json([
            'message' => ! empty($fish) ? 'success' : 'data not found',
            'data' => ! empty($fish) ? $fish : null,
        ]);
    }

    public function create(CreateFishRequest $request)
    {
        try {
            $fish = Fish::create($request->validated());

            return response()->json(['message' => 'fish created successfully', 'data' => $fish], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'fish created failed', 'data' => $e->errors()], 400);
        }

    }
}
