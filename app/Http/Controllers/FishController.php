<?php

namespace App\Http\Controllers;

use App\Contracts\CaptureSessionServiceInterface;
use App\Contracts\FishSearchServiceInterface;
use App\Contracts\FishServiceInterface;
use App\Contracts\StorageServiceInterface;
use App\Http\Requests\BatchCreateFishRequest;
use App\Http\Requests\FishSearchRequest;
use App\Models\Fish;
use App\Models\FishTribeResponse;
use App\Services\AudioVisibilityService;
use App\Services\CaptureRecordBatchService;
use App\Services\LocationVisibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class FishController extends Controller
{
    /** 公開清單每筆魚只開放的欄位 */
    private const PUBLIC_ITEM_FIELDS = [
        'id',
        'name',
        'image_url',
        'display_image_url',
        'display_image_position',
        'display_image_scale',
    ];

    /** 前端 `searchOptions` 的固定鍵（公開清單全部回空陣列） */
    private const SEARCH_OPTION_KEYS = [
        'tribes',
        'dietaryClassifications',
        'processingMethods',
        'captureMethods',
        'captureLocations',
    ];

    protected $fishService;

    protected $storageService;

    protected $fishSearchService;

    protected $captureSessionService;

    protected $audioVisibilityService;

    protected $locationVisibilityService;

    protected CaptureRecordBatchService $captureRecordBatchService;

    public function __construct(
        FishServiceInterface $fishService,
        StorageServiceInterface $storageService,
        FishSearchServiceInterface $fishSearchService,
        CaptureSessionServiceInterface $captureSessionService,
        AudioVisibilityService $audioVisibilityService,
        LocationVisibilityService $locationVisibilityService,
        ?CaptureRecordBatchService $captureRecordBatchService = null
    ) {
        $this->fishService = $fishService;
        $this->storageService = $storageService;
        $this->fishSearchService = $fishSearchService;
        $this->captureSessionService = $captureSessionService;
        $this->audioVisibilityService = $audioVisibilityService;
        $this->locationVisibilityService = $locationVisibilityService;
        $this->captureRecordBatchService = $captureRecordBatchService ?? app(CaptureRecordBatchService::class);
    }

    public function getFish($id, Request $request)
    {
        $details = $this->fishService->getFishDetails((int) $id, includeSessionNotes: true);
        $details['tribes'] = config('fish_options.tribes');
        $details = $this->audioVisibilityService->filter($details, $request->user());
        $details = $this->locationVisibilityService->filter($details, $request->user());
        $details['responses'] = [
            'form' => FishTribeResponse::formOptions($details['fish']['name']),
            'summary' => FishTribeResponse::summaryForFish((int) $id, $request->user()->id),
            'mine' => FishTribeResponse::answersOf($request->user()->id, (int) $id),
            'responders' => FishTribeResponse::responderCount((int) $id),
        ];

        return Inertia::render('Fish', $details);
    }

    public function getFishs(FishSearchRequest $request)
    {
        // Trace: FR-001 多條件後端搜尋, FR-003 比對規則, FR-007 perPage 正規化, FR-005 游標分頁, FR-002 精簡欄位
        // Trace: SC-004 首屏 payload 降幅（僅回傳白名單欄位給前端無限滾動）, SC-006 游標契約一致性（last_id 明碼, nextCursor 派生）
        // 清單內容依身分而異：歷史紀錄加密，登入或登出時換金鑰，返回上一頁就不會還原舊身分的清單
        Inertia::encryptHistory();

        if ($request->isPublicView()) {
            return $this->renderPublicFishs($request);
        }

        // 使用 Request 清洗參數（忽略空白、perPage 正規化、游標驗證）
        $filters = $this->locationSafeFilters($request->cleaned(), $request);

        // 游標式分頁 + 精簡欄位（提供給前端無限滾動使用）
        $paginated = $this->fishSearchService->paginate($filters);

        $searchOptions = $this->locationSafeSearchOptions(
            $this->fishSearchService->getSearchOptions(),
            $request
        );
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
            'items' => $this->locationVisibilityService->filter(
                $this->audioVisibilityService->filter($paginated['items'], $request->user()),
                $request->user()
            ),
            'pageInfo' => $paginated['pageInfo'],
            'view' => $filters['view'],
            'viewGroupCounts' => $paginated['groupCounts'] ?? null,
            // 目前看法的名稱：不依賴選項計算，往下載入的回應也帶，歷史返回時橫條仍有名稱
            'viewMeta' => $this->viewMeta($filters['view']),
            // 選項只在第一頁計算：往下載入（帶游標）不帶這個欄位；第一頁計算失敗則為 null
            ...(isset($filters['last_id']) ? [] : ['viewOptions' => $this->viewOptionsOrNull()]),
        ]);
    }

    /** 目前看法的鍵、標題與說明；不分類時沒有 */
    private function viewMeta(string $view): ?array
    {
        $config = $view === 'all' ? null : config("fish_options.list_views.$view");

        return $config === null ? null : ['key' => $view, 'label' => $config['label'], 'hint' => $config['hint']];
    }

    /** 選項計算失敗時記錄錯誤並降級（回 null，前端不顯示小視窗），不影響清單本身 */
    private function viewOptionsOrNull(): ?array
    {
        try {
            return $this->fishSearchService->getViewOptions();
        } catch (\Throwable $e) {
            Log::error('魚類清單看法選項計算失敗', ['exception' => $e]);

            return null;
        }
    }

    /**
     * 公開清單：只留白名單欄位（魚名與照片）與白名單參數（name、last_id、perPage）。
     */
    private function renderPublicFishs(FishSearchRequest $request)
    {
        // 禁用的篩選參數已在 FishSearchRequest 驗證前排除
        $filters = $request->cleaned();

        $paginated = $this->fishSearchService->paginate($filters);

        return Inertia::render('Fishs', [
            'filters' => array_filter(['name' => $filters['name']], fn ($value) => $value !== null && $value !== ''),
            'searchOptions' => array_fill_keys(self::SEARCH_OPTION_KEYS, []),
            'searchStats' => new \stdClass,
            'items' => array_map(
                fn (array $item) => array_intersect_key($item, array_flip(self::PUBLIC_ITEM_FIELDS)),
                $paginated['items']
            ),
            'pageInfo' => $paginated['pageInfo'],
        ]);
    }

    public function search(Request $request)
    {
        $filters = $this->locationSafeFilters(
            $request->only(['name', 'tribe', 'dietary_classification', 'processing_method', 'capture_location', 'capture_method']),
            $request
        );

        $fishs = $this->fishSearchService->search($filters);
        $searchOptions = $this->locationSafeSearchOptions(
            $this->fishSearchService->getSearchOptions(),
            $request
        );
        $searchStats = $this->fishSearchService->getSearchStats($filters);

        return Inertia::render('Fish/Search', [
            'fishs' => $this->locationVisibilityService->filter(
                $this->audioVisibilityService->filter($fishs, $request->user()),
                $request->user()
            ),
            'filters' => $filters,
            'searchOptions' => $searchOptions,
            'searchStats' => $searchStats,
        ]);
    }

    private function locationSafeFilters(array $filters, Request $request): array
    {
        if (! $request->user()?->canAccessLocation()) {
            unset($filters['capture_location']);
        }

        return $filters;
    }

    private function locationSafeSearchOptions(array $options, Request $request): array
    {
        if (! $request->user()?->canAccessLocation()) {
            unset($options['captureLocations']);
        }

        return $options;
    }

    /**
     * 顯示批次新增魚類頁面。
     */
    public function batchCreate()
    {
        return Inertia::render('BatchCreateFish', [
            'tribes' => config('fish_options.tribes'),
            'capture_methods' => config('fish_options.capture_methods'),
            'upload_limits' => config('fish_options.batch_upload'),
            'selectable_sessions' => $this->captureSessionService->getSelectableSessions(),
            'legacy_combos' => $this->captureSessionService->getLegacyCombos(),
        ]);
    }

    /**
     * 批次建立魚類 + 多筆捕獲紀錄（單一 transaction）。
     */
    public function batchStore(BatchCreateFishRequest $request)
    {
        $validated = $request->validated();
        $filenames = $validated['filenames'];
        $name = filled($validated['name'] ?? null) ? $validated['name'] : '我不知道';

        $fish = DB::transaction(function () use ($validated, $filenames, $name) {
            $fish = Fish::create([
                'name' => $name,
                'image' => $filenames[0],
            ]);

            $records = $this->captureRecordBatchService->createForFishFromSession(
                $fish,
                $filenames,
                $validated['session_id'] ?? null,
                $validated['legacy_combo'] ?? null,
                $validated['notes'] ?? null,
            );

            $fish->update(['display_capture_record_id' => $records[0]->id]);

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
            'fish' => $fish,
        ]);
    }

    public function updateName(Request $request, $id)
    {
        // 詳細的除錯資訊
        Log::info('=== 更新魚類名稱 DEBUG ===');
        Log::info('Request method: '.$request->method());
        Log::info('Request URL: '.$request->fullUrl());
        Log::info('Request path: '.$request->path());
        Log::info('Fish ID: '.$id);
        Log::info('Request data: ', $request->all());
        Log::info('Headers: ', $request->headers->all());
        Log::info('========================');

        $fish = Fish::findOrFail($id);
        $request->validate(['name' => 'required|string|max:255']);
        $fish->update(['name' => $request->name]);

        Log::info('魚類名稱已更新: '.$fish->name);

        // 加入 redirect + flash message
        return redirect("/fish/{$id}?tab=basic")
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
                'fish_name' => $fishName,
            ]);

            // 使用標準 Inertia 流程：redirect + flash message
            return redirect('/fishs')->with('success', "魚類「{$fishName}」已成功刪除！");

        } catch (\Exception $e) {
            Log::error('魚類刪除錯誤: '.$e->getMessage(), [
                'fish_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', '刪除魚類時發生錯誤：'.$e->getMessage());
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
            'capture_record_id' => 'required|integer|exists:capture_records,id',
        ]);

        $fish = Fish::findOrFail($id);
        $captureRecordId = $request->input('capture_record_id');

        // 驗證捕獲紀錄是否屬於這條魚
        $captureRecord = $fish->captureRecords()->where('id', $captureRecordId)->first();

        if (! $captureRecord) {
            return back()->withErrors(['capture_record_id' => '捕獲紀錄不屬於此魚類']);
        }

        $fish->update([
            'display_capture_record_id' => $captureRecordId,
        ]);

        return back()->with('success', '已設定為圖鑑主圖');
    }

    /**
     * 顯示魚類合併頁面
     *
     * @param  int  $id  目標魚類 ID
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
