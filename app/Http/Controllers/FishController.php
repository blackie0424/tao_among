<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\CreateFishRequest;
use App\Http\Requests\UpdateFishRequest;
use App\Models\Fish;
use App\Models\FishNote;
use App\Models\TribalClassification;
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
        
        // 定義部落和分類選項
        $tribes = ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley'];
        $foodCategories = ['oyod', 'rahet', '不分類', '不食用', '?', ''];
        $processingMethods = ['去魚鱗', '不去魚鱗', '剝皮', '不食用', '?', ''];
        
        return Inertia::render('TribalClassifications', [
            'fish' => $fish,
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
        
        // 定義部落和分類選項
        $tribes = ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley'];
        $foodCategories = ['oyod', 'rahet', '不分類', '不食用', '?', ''];
        $processingMethods = ['去魚鱗', '不去魚鱗', '剝皮', '不食用', '?', ''];
        
        return Inertia::render('CreateTribalClassification', [
            'fish' => $fish,
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
        
        // 定義部落和分類選項
        $tribes = ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley'];
        $foodCategories = ['oyod', 'rahet', '不分類', '不食用', '?', ''];
        $processingMethods = ['去魚鱗', '不去魚鱗', '剝皮', '不食用', '?', ''];
        
        return Inertia::render('EditTribalClassification', [
            'fish' => $fish,
            'classification' => $classification,
            'tribes' => $tribes,
            'foodCategories' => $foodCategories,
            'processingMethods' => $processingMethods
        ]);
    }

}
