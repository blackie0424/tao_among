<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Contracts\StorageServiceInterface;
use App\Http\Requests\CreateFishRequest;
use App\Http\Requests\UpdateFishRequest;
use App\Models\Fish;
use App\Models\FishNote;
use App\Models\CaptureRecord;
use App\Services\FishService;
use App\Services\FishSearchService;
use App\Http\Requests\FishSearchRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Carbon\Carbon;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class FishController extends Controller
{
    protected $fishService;
    protected $storageService;
    protected $fishSearchService;

    public function __construct(FishService $fishService, StorageServiceInterface $storageService, FishSearchService $fishSearchService)
    {
        $this->fishService = $fishService;
        $this->storageService = $storageService;
        $this->fishSearchService = $fishSearchService;
    }

    public function index()
    {
        return Inertia::render('Index');
    }

    public function getFish($id, Request $request)
    {
        $details = $this->fishService->getFishDetails((int) $id);

        return Inertia::render('Fish', $details);
    }

    public function getFishs(FishSearchRequest $request)
    {
        // Trace: FR-001 多條件後端搜尋, FR-003 比對規則, FR-007 perPage 正規化, FR-005 游標分頁, FR-002 精簡欄位
        // Trace: SC-004 首屏 payload 降幅（僅回傳白名單欄位給前端無限滾動）, SC-006 游標契約一致性（last_id 明碼, nextCursor 派生）
        // 使用 Request 清洗參數（忽略空白、perPage 正規化、游標驗證）
        $filters = $request->cleaned();

        // 游標式分頁 + 精簡欄位（提供給前端無限滾動使用）
        $paginated = $this->fishSearchService->paginate($filters);

        // 保持相容：沿用舊的完整集合供現有 Inertia 頁面（測試）檢查 image 屬性（後續可移除）
        $legacyFilters = $request->only(['name', 'tribe', 'dietary_classification', 'processing_method', 'capture_location', 'capture_method']);
        $fishs = $this->fishSearchService->search($legacyFilters);

        $searchOptions = $this->fishSearchService->getSearchOptions();
        $searchStats = $this->fishSearchService->getSearchStats($legacyFilters);
        return Inertia::render('Fishs', [
            'fishs' => $fishs,
            'filters' => $legacyFilters,
            'searchOptions' => $searchOptions,
            'searchStats' => $searchStats,
            // 新增契約格式（FR-002, FR-005）
            'items' => $paginated['items'],
            'pageInfo' => $paginated['pageInfo'],
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

    public function edit($id)
    {
        // 取得指定魚類資訊
        $fish = Fish::findOrFail($id);
        // 回傳編輯畫面，帶入魚類資訊
        return Inertia::render('EditFishName', [
            'fish' => $fish
        ]);
    }

    public function updateName(Request $request, $id)
    {
        // 詳細的除錯資訊
        Log::info('=== 更新魚類名稱 DEBUG ===');
        Log::info('Request method: ' . $request->method());
        Log::info('Request URL: ' . $request->fullUrl());
        Log::info('Request path: ' . $request->path());
        Log::info('Fish ID: ' . $id);
        Log::info('Request data: ', $request->all());
        Log::info('Headers: ', $request->headers->all());
        Log::info('========================');
        
        $fish = Fish::findOrFail($id);
        $request->validate(['name' => 'required|string|max:255']);
        $fish->update(['name' => $request->name]);
        
        Log::info('魚類名稱已更新: ' . $fish->name);
        
        // 加入 redirect + flash message
        return redirect("/fish/{$id}")
            ->with('success', "魚類名稱已更新為「{$fish->name}」！");
    }

    public function destroy($id)
    {
        try {
            $fish = Fish::findOrFail($id);
            $fishName = $fish->name;
            
            // 執行軟刪除（會自動觸發級聯刪除）
            $fish->delete();
            
            Log::info('魚類刪除成功', [
                'fish_id' => $id,
                'fish_name' => $fishName
            ]);
            
            // 使用標準 Inertia 流程：redirect + flash message
            return redirect('/fishs')->with('success', "魚類「{$fishName}」已成功刪除！");
            
        } catch (\Exception $e) {
            Log::error('魚類刪除錯誤: ' . $e->getMessage(), [
                'fish_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return back()->with('error', '刪除魚類時發生錯誤：' . $e->getMessage());
        }
    }

    public function store(CreateFishRequest $request)
    {
        try {
            $fish = Fish::create($request->validated());
            
            // 自動建立首次捕獲紀錄（方案B：確保圖片有對應的 capture_record）
            $captureRecord = CaptureRecord::create([
                'fish_id' => $fish->id,
                'image_path' => $request->validated()['image'],
                'tribe' => 'iraraley', // 使用第一個有效的部落
                'location' => '待補充',
                'capture_method' => 'mamasil',
                'capture_date' => now(),
                'notes' => '首次建立時自動產生，請至捕獲紀錄頁面補充資訊'
            ]);
            
            // 自動設定為圖鑑主圖
            $fish->update(['display_capture_record_id' => $captureRecord->id]);
            
            // 使用 redirect + flash message 統一流程
            return redirect("/fish/{$fish->id}")
                ->with('success', "魚類「{$fish->name}」新增成功！");
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'data' => ['errors' => $e->errors()],
            ], 422);
            
        }
    }

    /**
     * 更新圖鑑顯示圖片（在不同捕獲紀錄之間切換）
     *
     * 注意：此 API 不支援傳入 null 重設為原始圖片
     * 重設為 null 只會由系統自動處理（ON DELETE SET NULL 或軟刪除 fallback）
     */
    public function updateDisplayImage(Request $request, $id)
    {
        $request->validate([
            'capture_record_id' => 'required|integer|exists:capture_records,id'
        ]);

        $fish = Fish::findOrFail($id);
        $captureRecordId = $request->input('capture_record_id');

        // 驗證捕獲紀錄是否屬於這條魚
        $captureRecord = $fish->captureRecords()->where('id', $captureRecordId)->first();
        
        if (!$captureRecord) {
            return back()->withErrors(['capture_record_id' => '捕獲紀錄不屬於此魚類']);
        }

        $fish->update([
            'display_capture_record_id' => $captureRecordId
        ]);

        return back()->with('success', '已設定為圖鑑主圖');
    }

}
