<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\CreateFishRequest;
use App\Http\Requests\UpdateFishRequest;
use App\Models\Fish;
use App\Models\FishNote;
use App\Models\TribalClassification;
use App\Models\CaptureRecord;
use App\Services\FishService;
use App\Services\SupabaseStorageService;
use App\Services\FishSearchService;
use App\Http\Requests\TribalClassificationRequest;
use App\Http\Requests\CaptureRecordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\FishSize;

class FishController extends Controller
{
    protected $fishService;
    protected $supabaseStorage;
    protected $fishSearchService;

    public function __construct(FishService $fishService, SupabaseStorageService $supabaseStorage, FishSearchService $fishSearchService)
    {
        $this->fishService = $fishService;
        $this->supabaseStorage = $supabaseStorage;
        $this->fishSearchService = $fishSearchService;
    }

    public function index()
    {
        return Inertia::render('Index');
    }

    public function getFish($id, Request $request)
    {
        $fish = $this->fishService->getFishById($id);
        
        // 取得部落分類資料（最多顯示前5筆）
        $tribalClassifications = TribalClassification::where('fish_id', $id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        // 取得捕獲紀錄資料（最多顯示前4筆）
        // CaptureRecord 模型已經自動處理 image_url 屬性
        $captureRecords = CaptureRecord::where('fish_id', $id)
            ->orderBy('capture_date', 'desc')
            ->limit(4)
            ->get();

        // 取得 fish_note 資訊，並依 note_type 分組
        $fishNotes = FishNote::where('fish_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        // 依 note_type 分組，並將每組的 collection 轉為 index keys 的陣列
        $groupedFishNotes = $fishNotes
            ->groupBy('note_type')
            ->map(function ($items) {
                return $items->values()->toArray();
            })->toArray();
            
        return Inertia::render('Fish', [
            'fish' => $fish,
            'tribalClassifications' => $tribalClassifications,
            'captureRecords' => $captureRecords,
            'fishNotes' => $groupedFishNotes
        ]);
    }

    public function getFishs(Request $request)
    {
        $filters = $request->only(['name', 'tribe', 'dietary_classification', 'processing_method', 'capture_location', 'capture_method']);
        $fishs = $this->fishSearchService->search($filters);
        $searchOptions = $this->fishSearchService->getSearchOptions();
        $searchStats = $this->fishSearchService->getSearchStats($filters);
        return Inertia::render('Fishs', [
            'fishs' => $fishs,
            'filters' => $filters,
            'searchOptions' => $searchOptions,
            'searchStats' => $searchStats,
        ]);
    }

    public function search(Request $request)
    {
        $filters = $request->only(['name', 'tribe', 'dietary_classification', 'processing_method', 'capture_location', 'capture_method']);
        
        $fishs = $this->fishSearchService->search($filters);
        $searchOptions = $this->fishSearchService->getSearchOptions();
        $searchStats = $this->fishSearchService->getSearchStats($filters);

        return Inertia::render('Fish/Search', [
            'fishs' => $fishs,
            'filters' => $filters,
            'searchOptions' => $searchOptions,
            'searchStats' => $searchStats,
        ]);
    }

    public function create()
    {
        return Inertia::render('CreateFish');
    }
    public function createAudio()
    {
        return Inertia::render('CreateFishAudio');
    }

    public function edit($id)
    {
        // 取得指定魚類資訊
        $fish = Fish::findOrFail($id);
        // 回傳編輯畫面，帶入魚類資訊
        return Inertia::render('EditFishName', [
            'fish' => $fish
        ]);
    }

    public function editSize($id)
    {
        // 用 fish_id 查詢 fish_size 物件
        $fishSize = FishSize::where('fish_id', $id)->firstOrFail();
        // 回傳編輯畫面，帶入魚類尺寸資訊
        return Inertia::render('EditFishSize', [
            'fishSize' => $fishSize
        ]);
    }

    public function tribalClassifications($id)
    {
        // 取得指定魚類資訊和部落分類
        $fish = Fish::with('tribalClassifications')->findOrFail($id);
        
        // 使用 FishService 處理圖片 URL
        $fishWithImage = $this->fishService->assignImageUrls([$fish])[0];
        
        // 定義部落和分類選項
        $tribes = ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley'];
        $foodCategories = ['oyod', 'rahet', '不分類', '不食用', '?', ''];
        $processingMethods = ['去魚鱗', '不去魚鱗', '剝皮', '不食用', '?', ''];
        
        return Inertia::render('TribalClassifications', [
            'fish' => $fishWithImage,
            'tribes' => $tribes,
            'foodCategories' => $foodCategories,
            'processingMethods' => $processingMethods
        ]);
    }

    public function storeTribalClassification(TribalClassificationRequest $request, $fishId)
    {
        $fish = Fish::findOrFail($fishId);
        
        TribalClassification::create([
            'fish_id' => $fish->id,
            'tribe' => $request->tribe,
            'food_category' => $request->food_category ?? '',
            'processing_method' => $request->processing_method ?? '',
            'notes' => $request->notes
        ]);

        return redirect()->back()->with('success', '部落分類新增成功');
    }

    public function updateTribalClassification(TribalClassificationRequest $request, $fishId, $classificationId)
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

        return redirect()->back()->with('success', '部落分類更新成功');
    }

    public function destroyTribalClassification($fishId, $classificationId)
    {
        $classification = TribalClassification::where('fish_id', $fishId)
            ->where('id', $classificationId)
            ->firstOrFail();
            
        $classification->delete();

        return redirect()->back()->with('success', '部落分類刪除成功');
    }

    public function createTribalClassification($fishId)
    {
        $fish = Fish::findOrFail($fishId);
        
        // 使用 FishService 處理圖片 URL
        $fishWithImage = $this->fishService->assignImageUrls([$fish])[0];
        
        // 定義部落和分類選項
        $tribes = ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley'];
        $foodCategories = ['oyod', 'rahet', '不分類', '不食用', '?', ''];
        $processingMethods = ['去魚鱗', '不去魚鱗', '剝皮', '不食用', '?', ''];
        
        return Inertia::render('CreateTribalClassification', [
            'fish' => $fishWithImage,
            'tribes' => $tribes,
            'foodCategories' => $foodCategories,
            'processingMethods' => $processingMethods
        ]);
    }

    public function editTribalClassification($fishId, $classificationId)
    {
        $fish = Fish::findOrFail($fishId);
        $classification = TribalClassification::where('fish_id', $fishId)
            ->where('id', $classificationId)
            ->firstOrFail();
        
        // 使用 FishService 處理圖片 URL
        $fishWithImage = $this->fishService->assignImageUrls([$fish])[0];
        
        // 定義部落和分類選項
        $tribes = ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley'];
        $foodCategories = ['oyod', 'rahet', '不分類', '不食用', '?', ''];
        $processingMethods = ['去魚鱗', '不去魚鱗', '剝皮', '不食用', '?', ''];
        
        return Inertia::render('EditTribalClassification', [
            'fish' => $fishWithImage,
            'classification' => $classification,
            'tribes' => $tribes,
            'foodCategories' => $foodCategories,
            'processingMethods' => $processingMethods
        ]);
    }

    // 捕獲紀錄相關方法
    public function captureRecords($fishId)
    {
        // 取得指定魚類資訊和捕獲紀錄
        $fish = Fish::with('captureRecords')->findOrFail($fishId);
        
        // 使用 FishService 處理魚類圖片 URL
        $fishWithImage = $this->fishService->assignImageUrls([$fish])[0];
        
        // 確保 captureRecords 以正確的鍵名傳遞
        $fishData = $fishWithImage->toArray();
        $fishData['captureRecords'] = $fishWithImage->captureRecords->toArray();
        
        // 定義部落選項
        $tribes = ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley'];
        
        return Inertia::render('CaptureRecords', [
            'fish' => $fishData,
            'tribes' => $tribes
        ]);
    }

    public function createCaptureRecord($fishId)
    {
        $fish = Fish::findOrFail($fishId);
        
        // 使用 FishService 處理圖片 URL
        $fishWithImage = $this->fishService->assignImageUrls([$fish])[0];
        
        // 定義部落選項
        $tribes = ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley'];
        
        return Inertia::render('CreateCaptureRecord', [
            'fish' => $fishWithImage,
            'tribes' => $tribes
        ]);
    }

    public function storeCaptureRecord(CaptureRecordRequest $request, $fishId)
    {
        $fish = Fish::findOrFail($fishId);

        $validated = $request->validated();

        // 檢查是否有圖片檔案名稱（前端已經上傳完成）
        if (empty($validated['image_filename'])) {
            return redirect()->back()->withErrors(['image' => '請上傳捕獲照片'])->withInput();
        }

        CaptureRecord::create([
            'fish_id' => $fish->id,
            'image_path' => $validated['image_filename'],
            'tribe' => $validated['tribe'],
            'location' => $validated['location'],
            'capture_method' => $validated['capture_method'],
            'capture_date' => $validated['capture_date'],
            'notes' => $validated['notes']
        ]);

        return redirect()->route('fish.capture-records', $fishId)->with('success', '捕獲紀錄新增成功');
    }

    public function editCaptureRecord($fishId, $recordId)
    {
        $fish = Fish::findOrFail($fishId);
        $record = CaptureRecord::where('fish_id', $fishId)
            ->where('id', $recordId)
            ->firstOrFail();
        
        // 使用 FishService 處理圖片 URL
        $fishWithImage = $this->fishService->assignImageUrls([$fish])[0];
        
        // 定義部落選項
        $tribes = ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley'];
        
        return Inertia::render('EditCaptureRecord', [
            'fish' => $fishWithImage,
            'record' => $record,
            'tribes' => $tribes
        ]);
    }

    public function updateCaptureRecord(CaptureRecordRequest $request, $fishId, $recordId)
    {
        $record = CaptureRecord::where('fish_id', $fishId)
            ->where('id', $recordId)
            ->firstOrFail();

        $validated = $request->validated();

        $updateData = [
            'tribe' => $validated['tribe'],
            'location' => $validated['location'],
            'capture_method' => $validated['capture_method'],
            'capture_date' => $validated['capture_date'],
            'notes' => $validated['notes']
        ];

        // 處理圖片更新（如果有新圖片檔名）
        if (!empty($validated['image_filename'])) {
            // 刪除舊圖片
            if ($record->image_path) {
                try {
                    $this->supabaseStorage->delete($record->image_path);
                } catch (\Exception $e) {
                    // 記錄錯誤但不阻止更新操作
                    \Log::error('Failed to delete old capture record image: ' . $e->getMessage());
                }
            }
            
            $updateData['image_path'] = $validated['image_filename'];
        }

        $record->update($updateData);

        // 重新載入捕獲紀錄頁面
        return redirect()->route('fish.capture-records', $fishId);
    }

    public function destroyCaptureRecord($fishId, $recordId)
    {
        \Log::info("Delete request received for fish: {$fishId}, record: {$recordId}");
        
        $record = CaptureRecord::where('fish_id', $fishId)
            ->where('id', $recordId)
            ->firstOrFail();
            
        // 執行軟刪除
        $record->delete();
        
        \Log::info("Record deleted successfully, redirecting to capture records");

        return redirect()->route('fish.capture-records', $fishId)->with('success', '捕獲紀錄刪除成功');
    }

    public function updateName(Request $request, $id)
    {
        $fish = Fish::findOrFail($id);
        
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $fish->update([
            'name' => $request->name,
        ]);

        return Inertia::render(
            'EditFishName',
            [
                'fish' => $fish
            ]
        );
    }

    public function updateSize(Request $request, $id)
    {
        $fish = Fish::findOrFail($id);
        
        $request->validate([
            'parts' => 'array',
        ]);

        // 找到或創建 FishSize 記錄
        $fishSize = FishSize::firstOrCreate(['fish_id' => $id]);
        
        $fishSize->update([
            'parts' => $request->parts ?? [],
        ]);

        return redirect("/fish/{$id}")->with('success', '魚類尺寸更新成功');
    }

    public function destroy($id)
    {
        try {
            $fish = Fish::findOrFail($id);
            
            // 執行軟刪除（會自動觸發級聯刪除）
            $fish->delete();
            
            // 檢查是否為 AJAX 請求
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => '魚類刪除成功'
                ]);
            }
            
            return redirect('/fishs')->with('success', '魚類刪除成功');
        } catch (\Exception $e) {
            \Log::error('魚類刪除錯誤: ' . $e->getMessage(), [
                'fish_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => '刪除魚類時發生錯誤: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect('/fishs')->with('error', '刪除魚類時發生錯誤: ' . $e->getMessage());
        }
    }

    public function store(CreateFishRequest $request)
    {
        try {
            $fish = Fish::create($request->validated());
            return Inertia::render(
                'CreateFish',
                [
                    'fish' => $fish
                ]
            );
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'data' => ['errors' => $e->errors()],
            ], 422);
            
        }
    }

}
