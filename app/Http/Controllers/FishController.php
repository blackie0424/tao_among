<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Contracts\StorageServiceInterface;
use App\Http\Requests\CreateFishRequest;
use App\Http\Requests\UpdateFishRequest;
use App\Models\Fish;
use App\Models\FishNote;
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
            Log::error('魚類刪除錯誤: ' . $e->getMessage(), [
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
                    'fish' => $fish,
                    'showCapturePrompt' => true,
                    'imageFileName' => $request->input('image')
                ]
            );
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'data' => ['errors' => $e->errors()],
            ], 422);
            
        }
    }

    public function updateAudioFilename(Request $request, $fishId, $audioId)
    {
        // 取出 fish 與指定 audio
        $fish = Fish::with('audios')->findOrFail($fishId);
        $audio = $fish->audios()->where('id', $audioId)->firstOrFail();

        // 更新主檔案欄位
        $fish->update([
            'audio_filename' => $audio->name,
        ]);
        // 由服務層統一處理媒體 URL（圖片 default、音檔 null-safe）
        $fish = $this->fishService->assignImageUrls([$fish])[0];
      
        // 使用 Inertia 回傳頁面與成功訊息，前端可由 props 取得 success
        return Inertia::render('FishAudioList', [
            'fish' => $fish,
            'success' => '魚類發音更新成功'
        ]);
    }

}
