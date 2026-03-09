<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\CreateFishRequest;
use App\Http\Requests\UpdateFishRequest;
use App\Models\Fish;
use App\Models\FishNote;
use App\Services\FishService;
use App\Services\FishSearchService;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class ApiFishController extends Controller
{

    protected $fishService;
    protected $fishSearchService;

    public function __construct(FishService $fishService, FishSearchService $fishSearchService)
    {
        $this->fishService = $fishService;
        $this->fishSearchService = $fishSearchService;
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
     *     path="/prefix/api/fish/{id}/compact",
     *     summary="取得單筆魚類精簡資料（與 Fishs 頁面 items 格式相容）",
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
    public function getCompactFishById($id): JsonResponse
    {
        $data = $this->fishSearchService->getCompactFishById((int) $id);

        if (!$data) {
            return response()->json([
                'message' => 'data not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'message' => 'success',
            'data' => $data,
        ]);
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
     * 搜尋魚類（用於合併頁面選擇器）
     *
     * @OA\Get(
     *     path="/prefix/api/fishs/search",
     *     summary="搜尋魚類（合併頁面）",
     *     tags={"Fish"},
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="exclude",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer"),
     *         description="排除的魚類 ID"
     *     ),
     *     @OA\Response(response=200, description="成功")
     * )
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->query('q');
        $excludeId = $request->query('exclude');

        if (!$query) {
            return response()->json([
                'message' => 'Query parameter is required',
                'data' => [],
            ], 400);
        }

        $fishes = Fish::where('name', 'LIKE', "%".strtolower($query)."%")
            ->when($excludeId, function ($q) use ($excludeId) {
                $q->where('id', '!=', $excludeId);
            })
            ->with([
                'captureRecords' => function ($query) {
                    // 載入完整捕獲紀錄資料，限制最多 10 筆
                    $query->select('id', 'fish_id', 'image_path', 'location', 'capture_method', 'capture_date', 'tribe', 'notes')
                          ->orderByDesc('capture_date')
                          ->limit(10);
                },
                'tribalClassifications' => function ($query) {
                    // 只載入 iraraley 和 imorod 兩個部落的分類
                    $query->whereIn('tribe', ['iraraley', 'imorod'])
                          ->select('id', 'fish_id', 'tribe', 'food_category');
                },
            ])
            ->limit(20)
            ->get()
            ->map(function ($fish) {
                // 組裝部落分類資訊
                $tribalClassifications = $fish->tribalClassifications->map(function ($tc) {
                    return [
                        'tribe' => $tc->tribe,
                        'food_category' => $tc->food_category,
                    ];
                })->toArray();

                // 組裝捕獲紀錄資訊（確保圖片不使用 WebP，LINE 可能不支援）
                $captureRecords = $fish->captureRecords->map(function ($record) {
                    // 直接取得不含 WebP 的圖片 URL
                    $storage = app(\App\Contracts\StorageServiceInterface::class);
                    $imageUrl = $record->image_path 
                        ? $storage->getUrl('images', $record->image_path, false) // 明確設定 hasWebp = false
                        : null;
                    
                    return [
                        'id' => $record->id,
                        'image_url' => $imageUrl,
                        'location' => $record->location,
                        'capture_method' => $record->capture_method,
                        'capture_date' => $record->capture_date?->format('Y-m-d'),
                        'tribe' => $record->tribe,
                        'notes' => $record->notes,
                    ];
                })->toArray();

                return [
                    'id' => $fish->id,
                    'name' => $fish->name,
                    'image_url' => $fish->image_url,
                    'display_image_url' => $fish->display_image_url,
                    'tribal_classifications' => $tribalClassifications,
                    'audio_url' => $fish->audio_url,
                    'capture_records' => $captureRecords,
                    'capture_records_count' => count($captureRecords),
                ];
            });

        return response()->json([
            'message' => 'success',
            'data' => $fishes,
        ]);
    }

    /**
     * 隨機取得一筆名稱為「我不知道」的魚類資料
     */
    public function randomUnknownFish(): JsonResponse
    {
        $fish = Fish::where('name', '我不知道')
            ->with([
                'captureRecords' => function ($query) {
                    $query->select('id', 'fish_id', 'image_path', 'location', 'capture_method', 'capture_date', 'tribe', 'notes')
                          ->orderByDesc('capture_date')
                          ->limit(10);
                },
                'tribalClassifications' => function ($query) {
                    $query->whereIn('tribe', ['iraraley', 'imorod'])
                          ->select('id', 'fish_id', 'tribe', 'food_category');
                },
            ])
            ->inRandomOrder()
            ->first();

        if (!$fish) {
            return response()->json([
                'message' => 'No unknown fish found',
                'data' => null,
            ], 404);
        }

        // 組裝部落分類資訊
        $tribalClassifications = $fish->tribalClassifications->map(function ($tc) {
            return [
                'tribe' => $tc->tribe,
                'food_category' => $tc->food_category,
            ];
        })->toArray();

        // 組裝捕獲紀錄資訊
        $captureRecords = $fish->captureRecords->map(function ($record) {
            $storage = app(\App\Contracts\StorageServiceInterface::class);
            $imageUrl = $record->image_path 
                ? $storage->getUrl('images', $record->image_path, false)
                : null;
            
            return [
                'id' => $record->id,
                'image_url' => $imageUrl,
                'location' => $record->location,
                'capture_method' => $record->capture_method,
                'capture_date' => $record->capture_date?->format('Y-m-d'),
                'tribe' => $record->tribe,
                'notes' => $record->notes,
            ];
        })->toArray();

        return response()->json([
            'message' => 'success',
            'data' => [
                'id' => $fish->id,
                'name' => $fish->name,
                'image_url' => $fish->image_url,
                'display_image_url' => $fish->display_image_url,
                'tribal_classifications' => $tribalClassifications,
                'audio_url' => $fish->audio_url,
                'capture_records' => $captureRecords,
                'capture_records_count' => count($captureRecords),
            ],
        ]);
    }

    /**
     * 依篩選條件取得魚類資料（供 LINE 圖文選單使用）
     * 支援 food_category 篩選或 tribe 篩選，每頁 10 筆
     */
    public function getFishesByFilter(Request $request): JsonResponse
    {
        $filterType = $request->query('filter_type'); // 'food_category' or 'tribe'
        $filterValue = $request->query('filter_value');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 10;

        if (!$filterType || !$filterValue) {
            return response()->json([
                'message' => 'filter_type and filter_value are required',
                'data' => [],
            ], 400);
        }

        $query = Fish::with([
            'tribalClassifications' => function ($q) {
                $q->select('id', 'fish_id', 'tribe', 'food_category');
            },
            'displayCaptureRecord',
        ]);

        if ($filterType === 'food_category') {
            // 透過 tribalClassifications 關聯篩選 food_category
            $query->whereHas('tribalClassifications', function ($q) use ($filterValue) {
                $q->where('food_category', strtolower($filterValue));
            });
        } elseif ($filterType === 'tribe') {
            // 透過 tribalClassifications 關聯篩選 tribe
            $query->whereHas('tribalClassifications', function ($q) use ($filterValue) {
                $q->where('tribe', strtolower($filterValue));
            });
        } else {
            return response()->json([
                'message' => 'Invalid filter_type. Use food_category or tribe.',
                'data' => [],
            ], 400);
        }

        $total = $query->count();
        $fishes = $query->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(function ($fish) {
                $tribalClassifications = $fish->tribalClassifications->map(function ($tc) {
                    return [
                        'tribe' => $tc->tribe,
                        'food_category' => $tc->food_category,
                    ];
                })->toArray();

                return [
                    'id' => $fish->id,
                    'name' => $fish->name,
                    'image_url' => $fish->image_url,
                    'display_image_url' => $fish->display_image_url,
                    'tribal_classifications' => $tribalClassifications,
                    'audio_url' => $fish->audio_url,
                    'audio_duration' => $fish->audio_duration,
                    'capture_records_count' => $fish->captureRecords()->count(),
                ];
            });

        return response()->json([
            'message' => 'success',
            'data' => $fishes,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'has_more' => ($page * $perPage) < $total,
        ]);
    }

    /**
     * 隨機取得多筆魚類資料（供 LINE 圖文選單「隨機瀏覽」使用）
     */
    public function getRandomFishes(Request $request): JsonResponse
    {
        $limit = min(10, max(1, (int) $request->query('limit', 10)));

        $fishes = Fish::with([
            'tribalClassifications' => function ($q) {
                $q->select('id', 'fish_id', 'tribe', 'food_category');
            },
            'displayCaptureRecord',
        ])
            ->inRandomOrder()
            ->take($limit)
            ->get()
            ->map(function ($fish) {
                $tribalClassifications = $fish->tribalClassifications->map(function ($tc) {
                    return [
                        'tribe' => $tc->tribe,
                        'food_category' => $tc->food_category,
                    ];
                })->toArray();

                return [
                    'id' => $fish->id,
                    'name' => $fish->name,
                    'image_url' => $fish->image_url,
                    'display_image_url' => $fish->display_image_url,
                    'tribal_classifications' => $tribalClassifications,
                    'audio_url' => $fish->audio_url,
                    'audio_duration' => $fish->audio_duration,
                    'capture_records_count' => $fish->captureRecords()->count(),
                ];
            });

        return response()->json([
            'message' => 'success',
            'data' => $fishes,
            'total' => $fishes->count(),
            'has_more' => false,
        ]);
    }
}
