<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\CreateFishRequest;
use App\Http\Requests\UpdateFishRequest;
use App\Models\Fish;
use App\Models\FishNote;
use App\Services\FishService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Carbon\Carbon;


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

    public function getFish($id,Request $request): View
    {
        $locate = $request->query('locate') ? strtolower($request->query('locate')) : 'iraraley';
        
        return view('fish', ['fish' =>$this->fishService->getFishByIdAndLocate($id,$locate)]);
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

    public function getFishById($id,Request $request): JsonResponse
    {
        try {
            $locate = $request->query('locate') ? strtolower($request->query('locate')) : 'iraraley';
            $fish = $this->fishService->getFishByIdAndLocate($id,$locate);
            return response()->json([
                'message' => 'success',
                'data' => $fish,
                'lastUpdateTime' => time()
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'data not found',
                'data' => null,
                'lastUpdateTime' => time()
            ], 404);
        }
    }

    public function getFishNotesSince($id,Request $request): JsonResponse
    {
        $since = $request->query('since');
        if ($since && !is_numeric($since)) {
            return response()->json([
                'message' => 'Invalid since parameter',
                'data' => null,
                'lastUpdateTime' => time()
            ], 400);
        }

        $sinceDate = $since ? Carbon::createFromTimestamp($since) : null;

        if($since){
            $notes = FishNote::where('fish_id', $id)->where('created_at', '>', $sinceDate)->get();
        }else{
            $notes = FishNote::where('fish_id', $id)->get();
        }

        if ($notes->isEmpty() && !Fish::find($id)) {
            return response()->json([
                'message' => 'Fish not found',
                'data' => null,
                'lastUpdateTime' => time()
            ], 404);
        }

        return response()->json([
            'message' => 'success',
            'data' => $notes,
            'lastUpdateTime' => time()
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

    public function addFishNote(Request $request, $id): JsonResponse
    {
        $request->validate([
            'note' => 'required|string',
            'note_type' => 'required|string|max:50',
            'locate' => 'required|string|max:20',
        ]);

        $fishNote = $this->fishService->addFishNote(
            $id, // 直接從路由參數取得 fish_id
            $request->note,
            $request->note_type,
            $request->locate
        );

        return response()->json([
            'message' => 'Note added successfully',
            'data' => $fishNote,
            'lastUpdateTime' => time()
        ], 201);
    }

    // 修正：使用 UpdateFishRequest 進行驗證
    public function update(UpdateFishRequest $request, $id): JsonResponse
    {
        // 取得驗證後的資料
        $validated = $request->validated();

        // 嘗試尋找並更新資料
        $fish = Fish::find($id);
        if (!$fish) {
            return response()->json([
                'message' => 'fish not found',
                'data' => null,
            ], 404);
        }

        $fish->update($validated);

        return response()->json([
            'message' => 'fish updated successfully',
            'data' => $fish,
        ]);
    }
}
