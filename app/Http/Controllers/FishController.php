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
use App\Http\Requests\TribalClassificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\FishSize;

class FishController extends Controller
{
    protected $fishService;

    public function __construct(FishService $fishService)
    {
        $this->fishService = $fishService;
    }

    public function index()
    {
        return Inertia::render('Index');
    }

    public function getFish($id, Request $request)
    {
        $locate = $request->query('locate') ? strtolower($request->query('locate')) : 'iraraley';
        $fish = $this->fishService->getFishByIdAndLocate($id, $locate);
        return Inertia::render('Fish', ['fish' => $fish]);
    }

    public function getFishs(Request $request)
    {
        $fishes = $this->fishService->getAllFishes();
        return Inertia::render('Fishs', [
            'fishes' => $fishes
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
        
        // 使用 FishService 處理圖片 URL
        $fishWithImage = $this->fishService->assignImageUrls([$fish])[0];
        
        // 定義部落選項
        $tribes = ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley'];
        
        return Inertia::render('CaptureRecords', [
            'fish' => $fishWithImage,
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

    public function storeCaptureRecord(Request $request, $fishId)
    {
        $fish = Fish::findOrFail($fishId);

        $validated = $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:10240', // 10MB
            'tribe' => 'required|in:ivalino,iranmeilek,imowrod,iratay,yayo,iraraley',
            'location' => 'required|string|max:255',
            'capture_method' => 'required|string|max:255',
            'capture_date' => 'required|date',
            'notes' => 'nullable|string|max:65535'
        ]);

        // 處理圖片上傳
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            
            // 這裡需要整合 Supabase 上傳
            // 暫時先儲存檔案名稱，稍後實作 Supabase 整合
            $imagePath = $imageName;
        }

        CaptureRecord::create([
            'fish_id' => $fish->id,
            'image_path' => $imagePath,
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

    public function updateCaptureRecord(Request $request, $fishId, $recordId)
    {
        $record = CaptureRecord::where('fish_id', $fishId)
            ->where('id', $recordId)
            ->firstOrFail();

        $validated = $request->validate([
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240', // 10MB
            'tribe' => 'required|in:ivalino,iranmeilek,imowrod,iratay,yayo,iraraley',
            'location' => 'required|string|max:255',
            'capture_method' => 'required|string|max:255',
            'capture_date' => 'required|date',
            'notes' => 'nullable|string|max:65535'
        ]);

        $updateData = [
            'tribe' => $validated['tribe'],
            'location' => $validated['location'],
            'capture_method' => $validated['capture_method'],
            'capture_date' => $validated['capture_date'],
            'notes' => $validated['notes']
        ];

        // 處理圖片上傳（如果有新圖片）
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            
            // 這裡需要整合 Supabase 上傳和刪除舊圖片
            $updateData['image_path'] = $imageName;
        }

        $record->update($updateData);

        return redirect()->route('fish.capture-records', $fishId)->with('success', '捕獲紀錄更新成功');
    }

    public function destroyCaptureRecord($fishId, $recordId)
    {
        $record = CaptureRecord::where('fish_id', $fishId)
            ->where('id', $recordId)
            ->firstOrFail();
            
        // 這裡需要刪除 Supabase 上的圖片
        
        $record->delete();

        return redirect()->back()->with('success', '捕獲紀錄刪除成功');
    }

}
