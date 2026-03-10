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
     * Show the form for creating/managing tribal classifications.
     */
    public function createPage($fishId)
    {
        $fish = Fish::with('tribalClassifications')->findOrFail($fishId);
        
        // 使用 Trait 處理圖片 URL
        $fishWithImage = $this->assignFishImage($fish);
        
        // 定義部落和分類選項
        $tribes = config('fish_options.tribes');
        $foodCategories = config('fish_options.food_categories');
        $processingMethods = config('fish_options.processing_methods');
        
        return Inertia::render('CreateTribalClassification', [
            'fish' => $fishWithImage,
            'tribes' => $tribes,
            'foodCategories' => $foodCategories,
            'processingMethods' => $processingMethods,
            'classifications' => $fish->tribalClassifications
        ]);
    }

    /**
     * Store or update multiple tribal classifications at once (batch mode).
     */
    public function storePage(Request $request, $fishId)
    {
        $fish = Fish::findOrFail($fishId);
        
        $request->validate([
            'classifications' => 'required|array',
            'classifications.*.tribe' => 'required|string|in:' . implode(',', config('fish_options.tribes')),
            'classifications.*.food_category' => 'nullable|string',
            'classifications.*.processing_method' => 'nullable|string',
            'classifications.*.notes' => 'nullable|string|max:65535',
        ]);

        foreach ($request->classifications as $data) {
            $tribe = $data['tribe'];
            $foodCategory = $data['food_category'] ?? '';
            $processingMethod = $data['processing_method'] ?? '';
            $notes = $data['notes'] ?? null;

            // Check if all fields are empty (meaning user wants to clear this tribe's data)
            $isEmptyData = empty($foodCategory) && empty($processingMethod) && empty($notes);

            $existingClassification = TribalClassification::where('fish_id', $fish->id)
                ->where('tribe', $tribe)
                ->first();

            $trashedClassification = TribalClassification::onlyTrashed()
                ->where('fish_id', $fish->id)
                ->where('tribe', $tribe)
                ->first();

            if ($isEmptyData) {
                // If it exists, soft delete it
                if ($existingClassification) {
                    $existingClassification->delete();
                }
                // If it doesn't exist, do nothing
            } else {
                if ($existingClassification) {
                    // Update existing
                    $existingClassification->update([
                        'food_category' => $foodCategory,
                        'processing_method' => $processingMethod,
                        'notes' => $notes
                    ]);
                } elseif ($trashedClassification) {
                    // Restore and update
                    $trashedClassification->restore();
                    $trashedClassification->update([
                        'food_category' => $foodCategory,
                        'processing_method' => $processingMethod,
                        'notes' => $notes
                    ]);
                } else {
                    // Create new
                    TribalClassification::create([
                        'fish_id' => $fish->id,
                        'tribe' => $tribe,
                        'food_category' => $foodCategory,
                        'processing_method' => $processingMethod,
                        'notes' => $notes
                    ]);
                }
            }
        }

        return redirect()->route('fish.knowledge-manager', ['id' => $fish->id])
            ->with('success', '地方知識已成功儲存/更新');
    }

    /**
     * Show the form for editing a tribal classification.
     */
    public function editPage($fishId, $classificationId)
    {
        // 編輯頁面目前也是顯示整個矩陣，因為可以直接一次修改所有部落
        $fish = Fish::with('tribalClassifications')->findOrFail($fishId);
        $classification = TribalClassification::where('fish_id', $fishId)
            ->where('id', $classificationId)
            ->firstOrFail(); // 若有需要可以拿來 hightlight，目前只要確保記錄存在
        
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
            'processingMethods' => $processingMethods,
            'classifications' => $fish->tribalClassifications
        ]);
    }

    /**
     * Update a tribal classification (from Inertia form).
     */
    public function updatePage(Request $request, $fishId, $classificationId)
    {
        // PUT 請求送進來，但我們一樣使用 Batch 更新邏輯
        return $this->storePage($request, $fishId);
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

        return redirect()->route('fish.knowledge-manager', ['id' => $fishId])
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
