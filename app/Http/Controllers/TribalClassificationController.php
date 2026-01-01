<?php

namespace App\Http\Controllers;

use App\Http\Requests\TribalClassificationRequest;
use App\Models\Fish;
use App\Models\TribalClassification;
use App\Services\FishService;
use App\Traits\HasFishImageUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TribalClassificationController extends Controller
{
    use HasFishImageUrl;

    protected $fishService;

    public function __construct(FishService $fishService)
    {
        $this->fishService = $fishService;
    }

    /**
     * Display a listing of tribal classifications for a specific fish (Inertia).
     */
    public function indexPage($fishId)
    {
        // 取得指定魚類資訊和部落分類
        $fish = Fish::with('tribalClassifications')->findOrFail($fishId);
        
        // 使用 Trait 處理圖片 URL
        $fishWithImage = $this->assignFishImage($fish);
        
        // 定義部落和分類選項
        $tribes = config('fish_options.tribes');
        $foodCategories = config('fish_options.food_categories');
        $processingMethods = config('fish_options.processing_methods');
        
        return Inertia::render('TribalClassifications', [
            'fish' => $fishWithImage,
            'tribes' => $tribes,
            'foodCategories' => $foodCategories,
            'processingMethods' => $processingMethods
        ]);
    }

    /**
     * Show the form for creating a new tribal classification.
     */
    public function createPage($fishId)
    {
        $fish = Fish::with('tribalClassifications')->findOrFail($fishId);
        
        // 使用 Trait 處理圖片 URL
        $fishWithImage = $this->assignFishImage($fish);
        
        // 定義部落和分類選項
        $allTribes = config('fish_options.tribes');
        $foodCategories = config('fish_options.food_categories');
        $processingMethods = config('fish_options.processing_methods');
        
        // 取得已記錄的部落
        $usedTribes = $fish->tribalClassifications->pluck('tribe')->toArray();
        
        // 過濾出尚未記錄的部落
        $availableTribes = array_values(array_diff($allTribes, $usedTribes));
        
        return Inertia::render('CreateTribalClassification', [
            'fish' => $fishWithImage,
            'tribes' => $availableTribes,
            'usedTribes' => $usedTribes,
            'foodCategories' => $foodCategories,
            'processingMethods' => $processingMethods
        ]);
    }

    /**
     * Store a newly created tribal classification (from Inertia form).
     */
    public function storePage(TribalClassificationRequest $request, $fishId)
    {
        $fish = Fish::findOrFail($fishId);
        
        // 檢查是否已存在相同部落的分類（不包含軟刪除）
        $existingClassification = TribalClassification::where('fish_id', $fish->id)
            ->where('tribe', $request->tribe)
            ->first();
            
        if ($existingClassification) {
            return redirect()->back()
                ->withErrors(['tribe' => '此魚類已有該部落的地方知識記錄，請直接編輯現有記錄或選擇其他部落。'])
                ->withInput();
        }
        
        // 檢查是否有軟刪除的記錄，若有則恢復並更新
        $trashedClassification = TribalClassification::onlyTrashed()
            ->where('fish_id', $fish->id)
            ->where('tribe', $request->tribe)
            ->first();
            
        if ($trashedClassification) {
            // 恢復軟刪除的記錄並更新資料
            $trashedClassification->restore();
            $trashedClassification->update([
                'food_category' => $request->food_category ?? '',
                'processing_method' => $request->processing_method ?? '',
                'notes' => $request->notes
            ]);
        } else {
            // 建立新記錄
            TribalClassification::create([
                'fish_id' => $fish->id,
                'tribe' => $request->tribe,
                'food_category' => $request->food_category ?? '',
                'processing_method' => $request->processing_method ?? '',
                'notes' => $request->notes
            ]);
        }

        return redirect()->route('fish.tribal-classifications', ['id' => $fish->id])
            ->with('success', '地方知識已成功新增');
    }

    /**
     * Show the form for editing a tribal classification.
     */
    public function editPage($fishId, $classificationId)
    {
        $fish = Fish::findOrFail($fishId);
        $classification = TribalClassification::where('fish_id', $fishId)
            ->where('id', $classificationId)
            ->firstOrFail();
        
        // 使用 Trait 處理圖片 URL
        $fishWithImage = $this->assignFishImage($fish);
        
        // 定義部落和分類選項
        $tribes = config('fish_options.tribes');
        $foodCategories = config('fish_options.food_categories');
        $processingMethods = config('fish_options.processing_methods');
        
        return Inertia::render('EditTribalClassification', [
            'fish' => $fishWithImage,
            'classification' => $classification,
            'tribes' => $tribes,
            'foodCategories' => $foodCategories,
            'processingMethods' => $processingMethods
        ]);
    }

    /**
     * Update a tribal classification (from Inertia form).
     */
    public function updatePage(TribalClassificationRequest $request, $fishId, $classificationId)
    {
        $classification = TribalClassification::where('fish_id', $fishId)
            ->where('id', $classificationId)
            ->firstOrFail();
            
        $classification->update([
            'tribe' => $request->tribe,
            'food_category' => $request->food_category ?? '',
            'processing_method' => $request->processing_method ?? '',
            'notes' => $request->notes
        ]);

        return redirect()->route('fish.tribal-classifications', ['id' => $fishId])
            ->with('success', '地方知識已成功更新');
    }

    /**
     * Delete a tribal classification (from Inertia page).
     */
    public function destroyPage($fishId, $classificationId)
    {
        $classification = TribalClassification::where('fish_id', $fishId)
            ->where('id', $classificationId)
            ->firstOrFail();
        
        $tribeName = $classification->tribe;
        $classification->delete();

        return redirect()->route('fish.tribal-classifications', ['id' => $fishId])
            ->with('success', "地方知識「{$tribeName}」已成功刪除");
    }

    /**
     * Display a listing of the resource for a specific fish (API).
     */
    public function index($fishId): JsonResponse
    {
        $fish = Fish::findOrFail($fishId);
        
        $classifications = $fish->tribalClassifications()
            ->orderBy('tribe')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Tribal classifications retrieved successfully',
            'data' => $classifications,
            'fish' => [
                'id' => $fish->id,
                'name' => $fish->name
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage (API).
     */
    public function store(TribalClassificationRequest $request, $fishId): JsonResponse
    {
        $fish = Fish::findOrFail($fishId);

        $validated = $request->validated();

        // 檢查是否已存在相同部落的分類（不包含軟刪除）
        $existingClassification = TribalClassification::where('fish_id', $fish->id)
            ->where('tribe', $validated['tribe'])
            ->first();
            
        if ($existingClassification) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => [
                    'tribe' => ['此魚類已有該部落的地方知識記錄，請直接編輯現有記錄或選擇其他部落。']
                ]
            ], 422);
        }
        
        // 檢查是否有軟刪除的記錄，若有則恢復並更新
        $trashedClassification = TribalClassification::onlyTrashed()
            ->where('fish_id', $fish->id)
            ->where('tribe', $validated['tribe'])
            ->first();
            
        if ($trashedClassification) {
            // 恢復軟刪除的記錄並更新資料
            $trashedClassification->restore();
            $trashedClassification->update([
                'food_category' => $validated['food_category'] ?? '',
                'processing_method' => $validated['processing_method'] ?? '',
                'notes' => $validated['notes']
            ]);
            $classification = $trashedClassification->fresh();
        } else {
            // 建立新記錄
            $classification = TribalClassification::create([
                'fish_id' => $fish->id,
                'tribe' => $validated['tribe'],
                'food_category' => $validated['food_category'] ?? '',
                'processing_method' => $validated['processing_method'] ?? '',
                'notes' => $validated['notes']
            ]);
        }

        return response()->json([
            'message' => 'Tribal classification created successfully',
            'data' => $classification
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $classification = TribalClassification::with('fish')->findOrFail($id);

        return response()->json([
            'message' => 'Tribal classification retrieved successfully',
            'data' => $classification
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(TribalClassificationRequest $request, string $id): JsonResponse
    {
        $classification = TribalClassification::findOrFail($id);

        $validated = $request->validated();

        $classification->update([
            'tribe' => $validated['tribe'],
            'food_category' => $validated['food_category'] ?? '',
            'processing_method' => $validated['processing_method'] ?? '',
            'notes' => $validated['notes']
        ]);

        return response()->json([
            'message' => 'Tribal classification updated successfully',
            'data' => $classification->fresh()
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $classification = TribalClassification::findOrFail($id);
        $classification->delete();

        return response()->json([
            'message' => 'Tribal classification deleted successfully'
        ]);
    }
}
