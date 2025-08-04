<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\CreateFishRequest;
use App\Http\Requests\UpdateFishRequest;
use App\Models\Fish;
use App\Models\FishNote;
use App\Services\FishService;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class ApiFishController extends Controller
{

    protected $fishService;

    public function __construct(FishService $fishService)
    {
        $this->fishService = $fishService;
    }

    /**
     * @OA\Get(
     *     path="/prefix/api/fish",
     *     summary="取得魚類列表",
     *     tags={"Fish"},
     *     @OA\Response(response=200, description="成功")
     * )
     */
    public function getFishs(Request $request): JsonResponse
    {
        $since = $request->query('since');
        if ($since !== null && (!is_numeric($since) || $since <= 0)) {
            return response()->json([
                'message' => 'Invalid since parameter',
                'data' => null,
                'lastUpdateTime' => null
            ], 400);
        }

        // 僅回傳未被軟刪除的資料
        $fishes = $since
            ? $this->fishService->getFishesBySince($since)
            : $this->fishService->getAllFishes();

        // 取得本次資料的最大 updated_at 作為 lastUpdateTime
        $lastUpdateTime = $fishes->isNotEmpty()
            ? strtotime($fishes->max('updated_at'))
            : null;

        return response()->json([
            'message' => $fishes->isNotEmpty() ? 'success' : 'No data available',
            'data' => $fishes->isNotEmpty() ? $fishes : [],
            'lastUpdateTime' => $lastUpdateTime
        ]);
    }


    /**
     * @OA\Get(
     *     path="/prefix/api/fish/{id}",
     *     summary="取得單一魚類資料",
     *     tags={"Fish"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="成功"),
     *     @OA\Response(response=404, description="找不到資料")
     * )
     */
    public function getFishById($id, Request $request): JsonResponse
    {
        try {
            $locate = $request->query('locate') ? strtolower($request->query('locate')) : 'iraraley';
            $fish = $this->fishService->getFishByIdAndLocate($id, $locate);
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


    /**
     * @OA\Get(
     *     path="/prefix/api/fish/{id}/notes",
     *     summary="取得指定魚類的筆記（可用 since 篩選）",
     *     tags={"Fish"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="since",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="成功")
     * )
     */
    public function getFishNotes($id, Request $request): JsonResponse
    {
        $since = $request->query('since');
        $locate = strtolower($request->query('locate'));
        if ($since && !is_numeric($since)) {
            return response()->json([
                'message' => 'Invalid since parameter',
                'data' => null,
                'lastUpdateTime' => time()
            ], 400);
        }

        $sinceDate = $since ? Carbon::createFromTimestamp($since) : null;

        if ($sinceDate) {
            $notes = FishNote::where('fish_id', $id)->where('created_at', '>', $sinceDate)->get();
        } elseif ($locate) {
            $notes = FishNote::where('fish_id', $id)->where('locate', $locate)->get();
        } else {
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

    
    /**
     * @OA\Post(
     *     path="/prefix/api/fish",
     *     summary="新增魚類",
     *     tags={"Fish"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "image"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="image", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="建立成功")
     * )
     */
    public function store(CreateFishRequest $request): JsonResponse
    {
        try {
            $fish = Fish::create($request->validated());

            return response()->json(['message' => 'fish created successfully', 'data' => $fish], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'fish created failed', 'data' => $e->errors()], 400);
        }

    }


    /**
     * @OA\Put(
     *     path="/prefix/api/fish/{id}",
     *     summary="更新魚類資料",
     *     tags={"Fish"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="image", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="更新成功"),
     *     @OA\Response(response=404, description="找不到資料")
     * )
     */
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

    /**
     * @OA\Delete(
     *     path="/prefix/api/fish/{id}",
     *     summary="刪除魚類",
     *     tags={"Fish"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="魚類 ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="刪除成功", @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Fish deleted successfully")
     *     )),
     *     @OA\Response(response=404, description="找不到魚類", @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="fish not found"),
     *         @OA\Property(property="data", type="string", example=null)
     *     ))
     * )
     */
    public function destroy($id): JsonResponse
    {
        $fish = Fish::find($id);
        if (!$fish) {
            return response()->json([
                'message' => 'fish not found',
                'data' => null,
            ], 404);
        }

        $fish->delete();

        return response()->json([
            'message' => 'Fish deleted successfully',
        ]);
    }

    /**
     * @OA\Put(
     *     path="/prefix/api/fish/{id}/editSize",
     *     summary="更新魚類尺寸",
     *     tags={"Fish"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="parts", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="更新成功"),
     *     @OA\Response(response=404, description="找不到資料")
     * )
     */
    public function updateSize(Request $request, $id): JsonResponse
    {
        $parts = $request->input('parts');
        if (!is_array($parts) || empty($parts)) {
            return response()->json([
                'message' => 'parts 欄位必須為陣列且不可為空',
                'data' => null,
            ], 422);
        }

        $fishSize = \App\Models\FishSize::where('fish_id', $id)->first();
        if (!$fishSize) {
            return response()->json([
                'message' => 'fish size not found',
                'data' => null,
            ], 404);
        }

        $fishSize->update(['parts' => $parts]);

        return response()->json([
            'status' => 'success',
            'message' => '更新成功',
            'data' => [
                'fish_id' => $id,
                'parts' => $parts,
            ],
        ]);
    }
}
