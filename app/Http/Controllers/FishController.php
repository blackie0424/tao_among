<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Contracts\StorageServiceInterface;
use App\Contracts\FishServiceInterface;
use App\Contracts\FishSearchServiceInterface;
use App\Contracts\CaptureSessionServiceInterface;
use App\Http\Requests\BatchCreateFishRequest;
use App\Http\Requests\UpdateFishRequest;
use Illuminate\Support\Facades\DB;
use App\Models\Fish;
use App\Models\FishNote;
use App\Models\CaptureRecord;
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
    protected $captureSessionService;

    public function __construct(
        FishServiceInterface $fishService,
        StorageServiceInterface $storageService,
        FishSearchServiceInterface $fishSearchService,
        CaptureSessionServiceInterface $captureSessionService
    ) {
        $this->fishService = $fishService;
        $this->storageService = $storageService;
        $this->fishSearchService = $fishSearchService;
        $this->captureSessionService = $captureSessionService;
    }

    public function index()
    {
        $user = auth()->user();

        if ($user && $user->isEditor()) {
            $cols = ['id', 'name', 'image'];

            $needAudio = Fish::doesntHave('audios')
                ->orderBy('id')
                ->limit(20)
                ->get($cols)
                ->map(fn ($f) => [
                    'id'        => $f->id,
                    'name'      => $f->name,
                    'image_url' => $f->image_url,
                ]);

            $needPhoto = Fish::where(function ($q) {
                    $q->whereNull('image')
                      ->orWhere('image', '')
                      ->orWhere('image', 'default.png');
                })
                ->orderBy('id')
                ->limit(20)
                ->get($cols)
                ->map(fn ($f) => [
                    'id'        => $f->id,
                    'name'      => $f->name,
                    'image_url' => null,
                ]);

            $recentEdits = Fish::orderByDesc('updated_at')
                ->limit(10)
                ->get($cols)
                ->map(fn ($f) => [
                    'id'        => $f->id,
                    'name'      => $f->name,
                    'image_url' => $f->image_url,
                ]);

            return Inertia::render('EditorHome', [
                'needAudio'   => $needAudio,
                'needPhoto'   => $needPhoto,
                'recentEdits' => $recentEdits,
            ]);
        }

        return Inertia::render('Index');
    }

    public function getFish($id, Request $request)
    {
        $details = $this->fishService->getFishDetails((int) $id);
        $details['tribes'] = config('fish_options.tribes');

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

        $searchOptions = $this->fishSearchService->getSearchOptions();
        // total_results 在 getSearchStats 內固定使用 Fish::count()，不受 $filters 影響
        // 但 tribe 需傳入以計算部落專屬統計（n, m）
        $searchStats = $this->fishSearchService->getSearchStats($filters);
        // 傳給前端的篩選條件：保留前端使用的 key 名（food_category）
        $frontendFilters = array_intersect_key($filters, array_flip(['name', 'tribe', 'food_category', 'processing_method', 'capture_location', 'capture_method', 'without_audio']));
        return Inertia::render('Fishs', [
            'filters' => $frontendFilters,
            'searchOptions' => $searchOptions,
            'searchStats' => $searchStats,
            // 精簡欄位 + 游標分頁（FR-002, FR-005）
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

    /**
     * 顯示批次新增魚類頁面。
     */
    public function batchCreate()
    {
        return Inertia::render('BatchCreateFish', [
            'tribes'          => config('fish_options.tribes'),
            'capture_methods' => config('fish_options.capture_methods'),
            'upload_limits'   => config('fish_options.batch_upload'),
            'recent_sessions' => $this->captureSessionService->getRecentSessions(),
        ]);
    }

    /**
     * 批次建立魚類 + 多筆捕獲紀錄（單一 transaction）。
     */
    public function batchStore(BatchCreateFishRequest $request)
    {
        $filenames     = $request->validated()['filenames'];
        $name          = filled($request->input('name')) ? $request->input('name') : '我不知道';
        $tribe         = $request->input('tribe', 'iraraley');
        $location      = $request->input('location', '待補充');
        $captureMethod = $request->input('capture_method', 'mamasil');
        $captureDate   = $request->input('capture_date', now()->toDateString());
        $notes         = $request->input('notes', null);

        $fish = DB::transaction(function () use ($name, $filenames, $tribe, $location, $captureMethod, $captureDate, $notes) {
            $fish = Fish::create([
                'name'  => $name,
                'image' => $filenames[0],
            ]);

            $firstRecordId = null;

            foreach ($filenames as $index => $filename) {
                $record = CaptureRecord::create([
                    'fish_id'        => $fish->id,
                    'image_path'     => $filename,
                    'tribe'          => $tribe,
                    'location'       => $location,
                    'capture_method' => $captureMethod,
                    'capture_date'   => $captureDate,
                    'notes'          => $notes,
                ]);

                if ($index === 0) {
                    $firstRecordId = $record->id;
                }
            }

            $fish->update(['display_capture_record_id' => $firstRecordId]);

            return $fish;
        });

        return redirect("/fish/{$fish->id}")
            ->with('success', "魚類「{$fish->name}」批次新增成功！");
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

    /**
     * 顯示魚類合併頁面
     *
     * @param int $id 目標魚類 ID
     * @return \Inertia\Response
     */
    public function showMergePage($id)
    {
        $fish = Fish::with(['captureRecords'])
            ->findOrFail($id);

        // 取得圖鑑主圖 URL（優先使用 display_image_url accessor）
        $imageUrl = $fish->display_image_url;

        return Inertia::render('MergeFish', [
            'fish' => [
                'id' => $fish->id,
                'name' => $fish->name,
                'image_url' => $imageUrl,
            ],
        ]);
    }

}
