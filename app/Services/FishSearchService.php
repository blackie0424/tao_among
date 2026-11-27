<?php

namespace App\Services;

use App\Contracts\FishSearchServiceInterface;
use App\Contracts\FishServiceInterface;
use App\Models\Fish;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FishSearchService — 後端搜尋核心服務
 * Trace: FR-001 多條件後端搜尋, FR-002 精簡欄位, FR-003 比對規則, FR-005 游標分頁,
 *        FR-007 perPage 正規化（搭配 Request）, FR-009 降低關聯載入, SC-004 payload 降幅
 */
class FishSearchService implements FishSearchServiceInterface
{
    protected $fishService;

    public function __construct(FishServiceInterface $fishService)
    {
        $this->fishService = $fishService;
    }

    /**
     * 執行魚類搜尋
     */
    public function search(array $filters)
    {
        $query = Fish::with(['tribalClassifications', 'captureRecords', 'displayCaptureRecord'])
            ->orderBy('id', 'desc'); // 最新的資料最先顯示

        $this->applyNameFilter($query, $filters);
        $this->applyTribalFilters($query, $filters);
        $this->applyCaptureFilters($query, $filters);

        $results = $query->get();

        // 將媒體 URL 組裝集中於服務層（圖片 default、音檔 null-safe）
        return $this->fishService->assignImageUrls($results);
    }

    /**
     * 套用名稱搜尋篩選
     */
    protected function applyNameFilter(Builder $query, array $filters)
    {
        if (! empty($filters['name'])) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }
    }

    /**
     * 套用部落相關篩選
     */
    protected function applyTribalFilters(Builder $query, array $filters)
    {
        $tribe = $filters['tribe'] ?? null;
        $foodCategory = $filters['food_category'] ?? $filters['dietary_classification'] ?? null;
        $processingMethod = $filters['processing_method'] ?? null;

        // 尚未紀錄：該部落完全沒有 tribal_classification 紀錄
        if ($foodCategory === '尚未紀錄' || $processingMethod === '尚未紀錄') {
            if ($tribe) {
                $query->whereDoesntHave('tribalClassifications', function ($q) use ($tribe) {
                    $q->where('tribe', $tribe);
                });
            } else {
                $query->doesntHave('tribalClassifications');
            }

            return;
        }

        $tribalFilters = array_filter([
            'tribe' => $tribe,
            'food_category' => $foodCategory,
            'processing_method' => $processingMethod,
        ]);

        if (! empty($tribalFilters)) {
            $query->whereHas('tribalClassifications', function ($q) use ($tribalFilters) {
                foreach ($tribalFilters as $field => $value) {
                    if ($field === 'processing_method') {
                        $q->where($field, 'like', '%'.$value.'%');
                    } else {
                        $q->where($field, $value);
                    }
                }
            });
        }
    }

    /**
     * 套用捕獲紀錄相關篩選
     */
    protected function applyCaptureFilters(Builder $query, array $filters)
    {
        $captureFilters = array_filter([
            'capture_location' => $filters['capture_location'] ?? null,
            'capture_method' => $filters['capture_method'] ?? null,
        ]);

        if (! empty($captureFilters)) {
            $query->whereHas('captureRecords', function ($q) use ($captureFilters) {
                if (! empty($captureFilters['capture_location'])) {
                    $q->where('location', 'like', '%'.$captureFilters['capture_location'].'%');
                }
                if (! empty($captureFilters['capture_method'])) {
                    $q->where('capture_method', 'like', '%'.$captureFilters['capture_method'].'%');
                }
            });
        }
    }

    /**
     * 取得搜尋選項
     */
    public function getSearchOptions()
    {
        return [
            'tribes' => config('fish_options.tribes', []),
            'dietaryClassifications' => config('fish_options.food_categories', []),
            'processingMethods' => config('fish_options.processing_methods', []),
            'captureMethods' => $this->getUniqueCaptureMethods(),
            'captureLocations' => $this->getUniqueCaptureLocations(),
        ];
    }

    /**
     * 取得唯一的處理方式
     */
    protected function getUniqueProcessingMethods()
    {
        return \App\Models\TribalClassification::whereNotNull('processing_method')
            ->where('processing_method', '!=', '')
            ->distinct()
            ->pluck('processing_method')
            ->toArray();
    }

    /**
     * 取得唯一的捕獲方式
     */
    protected function getUniqueCaptureMethods()
    {
        return \App\Models\CaptureRecord::whereNotNull('capture_method')
            ->where('capture_method', '!=', '')
            ->distinct()
            ->pluck('capture_method')
            ->toArray();
    }

    /**
     * 取得唯一的捕獲地點
     */
    protected function getUniqueCaptureLocations()
    {
        return \App\Models\CaptureRecord::whereNotNull('location')
            ->where('location', '!=', '')
            ->distinct()
            ->pluck('location')
            ->toArray();
    }

    /**
     * 建立搜尋查詢建構器
     */
    public function buildSearchQuery(array $filters)
    {
        $query = Fish::with(['tribalClassifications', 'captureRecords']);

        $this->applyNameFilter($query, $filters);
        $this->applyTribalFilters($query, $filters);
        $this->applyCaptureFilters($query, $filters);

        return $query;
    }

    /**
     * 取得搜尋結果統計
     * total_results 永遠是全部魚類總數（不受篩選影響，作為圖鑑基準數 x）
     * 若有 tribe 篩選，額外回傳該部落的分類紀錄數（n）與處理方式紀錄數（m）
     */
    public function getSearchStats(array $filters)
    {
        $tribe = $filters['tribe'] ?? null;

        return [
            // x：圖鑑總筆數，永遠固定，不受任何篩選影響
            'total_results' => Fish::count(),
            // 部落專屬統計（無 tribe 篩選時為 null）
            'tribe' => $tribe,
            'tribe_food_category_count' => $this->getTribeFoodCategoryCount($tribe),
            'tribe_processing_method_count' => $this->getTribeProcessingMethodCount($tribe),
        ];
    }

    /**
     * n：某部落中 food_category 已填寫的魚類（distinct fish_id）數量
     * 排除 null 與空字串（視為「尚未紀錄」）
     */
    private function getTribeFoodCategoryCount(?string $tribe): ?int
    {
        if (! $tribe) {
            return null;
        }

        return \App\Models\TribalClassification::where('tribe', $tribe)
            ->whereNotNull('food_category')
            ->where('food_category', '!=', '')
            ->distinct('fish_id')
            ->count('fish_id');
    }

    /**
     * m：某部落中 processing_method 已填寫的魚類（distinct fish_id）數量
     * 排除 null 與空字串（視為「尚未紀錄」）
     */
    private function getTribeProcessingMethodCount(?string $tribe): ?int
    {
        if (! $tribe) {
            return null;
        }

        return \App\Models\TribalClassification::where('tribe', $tribe)
            ->whereNotNull('processing_method')
            ->where('processing_method', '!=', '')
            ->distinct('fish_id')
            ->count('fish_id');
    }

    /**
     * 取得單筆魚類精簡資料（與 Fishs 頁面 items 格式相容）
     * 用於局部更新快取中的特定魚類
     *
     * @param  int  $id  魚類 ID
     * @return array|null 精簡格式資料，若找不到則回傳 null
     */
    public function getCompactFishById(int $id): ?array
    {
        $selects = ['id', 'name', 'image'];
        if (Schema::hasColumn('fish', 'has_webp')) {
            $selects[] = 'has_webp';
        }
        if (Schema::hasColumn('fish', 'audio_filename')) {
            $selects[] = 'audio_filename';
        }
        if (Schema::hasColumn('fish', 'display_capture_record_id')) {
            $selects[] = 'display_capture_record_id';
        }

        $fish = Fish::query()
            ->select($selects)
            ->with([
                'tribalClassifications:id,fish_id,tribe,food_category',
                'displayCaptureRecord:id,image_path,image_focus_x,image_focus_y,image_zoom,image_rotation',
            ])
            ->find($id);

        if (! $fish) {
            return null;
        }

        return [
            'id' => $fish->id,
            'name' => $fish->name,
            'image_url' => $fish->image_url,
            'display_image_url' => $fish->display_image_url,
            'display_image_framing' => $fish->display_image_framing,
            'audio_url' => $fish->audio_url,
            'tribal_classifications' => $fish->tribalClassifications->map(fn ($tc) => [
                'tribe' => $tc->tribe,
                'food_category' => $tc->food_category,
            ])->all(),
        ];
    }

    /**
     * 游標式分頁 + 精簡欄位（FR-001, FR-002, FR-005, FR-007, FR-009, SC-004）
     *
     * @param  array  $filters  cleaned filters (FishSearchRequest::cleaned)
     * @return array{items: array<int, array{id:int,name:string,image_url:string}>, pageInfo: array{hasMore:bool,nextCursor:int|null}}
     */
    public function paginate(array $filters): array
    {
        // Trace: FR-002 精簡欄位, FR-005 游標分頁, FR-007 正規化後 perPage 已由 Request 處理, SC-004 payload 降幅
        $perPage = (int) ($filters['perPage'] ?? config('fish_search.per_page_default'));
        $lastId = $filters['last_id'] ?? null;

        // 看法（晚上、白天、名字一樣、字母順序）不疊加其他搜尋條件
        $viewPage = $this->paginateView($filters, $perPage);
        if ($viewPage !== null) {
            return $viewPage;
        }

        $query = $this->listQuery()->orderByDesc('id');

        // 模糊/等值條件（FR-003）
        if (! empty($filters['name'])) {
            // FR-003 名稱模糊（大小寫不敏感）— 使用 LOWER + LIKE 以支援 SQLite/PG
            $query->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($filters['name']).'%']);
        }
        $this->applyListTribalFilters($query, $filters);
        $this->applyListCaptureFilters($query, $filters);
        // 無音檔篩選：全域顯示完全沒有 FishAudio 紀錄的魚類
        if (! empty($filters['without_audio'])) {
            $query->doesntHave('audios');
        }

        return $this->pageByIdCursor($query, $perPage, $lastId);
    }

    /** 部落分類篩選（FR-003）：tribe、food_category、processing_method */
    private function applyListTribalFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['tribe']) || ! empty($filters['food_category']) || ! empty($filters['processing_method'])) {
            $tribe = $filters['tribe'] ?? null;
            $food = $filters['food_category'] ?? null;
            $proc = $filters['processing_method'] ?? null;

            // 尚未紀錄：該部落完全沒有 tribal_classification 紀錄（FR-003 特殊值）
            if ($food === '尚未紀錄' || $proc === '尚未紀錄') {
                if ($tribe) {
                    $query->whereDoesntHave('tribalClassifications', function ($q) use ($tribe) {
                        $q->whereRaw('LOWER(tribe) = LOWER(?)', [$tribe]);
                    });
                } else {
                    $query->doesntHave('tribalClassifications');
                }
            } else {
                $query->whereHas('tribalClassifications', function ($q) use ($tribe, $food, $proc) {
                    if (! empty($tribe)) {
                        // FR-003 tribe 等值（LOWER=LOWER）
                        $q->whereRaw('LOWER(tribe) = LOWER(?)', [$tribe]);
                    }
                    if (! empty($food)) {
                        // FR-003 food_category 等值（LOWER=LOWER）
                        $q->whereRaw('LOWER(food_category) = LOWER(?)', [$food]);
                    }
                    if (! empty($proc)) {
                        // FR-003 processing_method 模糊大小寫不敏感
                        $q->whereRaw('LOWER(processing_method) LIKE ?', ['%'.strtolower($proc).'%']);
                    }
                });
            }
        }
    }

    /** 捕獲紀錄篩選（FR-003）：capture_location、capture_method（包含比對） */
    private function applyListCaptureFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['capture_location']) || ! empty($filters['capture_method'])) {
            $loc = $filters['capture_location'] ?? null;
            $met = $filters['capture_method'] ?? null;
            $query->whereHas('captureRecords', function ($q) use ($loc, $met) {
                if (! empty($loc)) {
                    // FR-003 capture_location 模糊大小寫不敏感
                    $q->whereRaw('LOWER(location) LIKE ?', ['%'.strtolower($loc).'%']);
                }
                if (! empty($met)) {
                    // FR-003 capture_method 模糊大小寫不敏感
                    $q->whereRaw('LOWER(capture_method) LIKE ?', ['%'.strtolower($met).'%']);
                }
            });
        }
    }

    // ── 清單看法（晚上、白天、名字一樣、字母順序）──────────────────

    /** 字母段以外的魚（空白、數字、符號、中文、帶重音字母開頭）歸此段，排在最後 */
    private const OTHER_SEGMENT = '其他';

    /**
     * 小視窗的選項與數字。optional 的看法數字為 0 時不列出。
     * 數字與該看法清單所有頁合計的條數一致。
     */
    public function getViewOptions(): array
    {
        $total = Fish::query()->count();
        $nameIndex = $this->nameIndex();

        $options = [];
        foreach (config('fish_options.list_views') as $key => $view) {
            $count = match ($key) {
                'night', 'day' => $this->countByCaptureMethods($view['capture_methods']),
                'same_name' => count($this->sameNameGroups($nameIndex)),
                default => $total,
            };
            if (($view['optional'] ?? false) && $count === 0) {
                continue;
            }
            $options[] = [
                'key' => $key,
                'label' => $view['label'],
                'hint' => $view['hint'],
                'count' => $count,
            ];
        }

        return $options;
    }

    /** 不是看法請求時回 null，由一般清單處理 */
    private function paginateView(array $filters, int $perPage): ?array
    {
        $view = $filters['view'] ?? 'all';
        $lastId = isset($filters['last_id']) ? (int) $filters['last_id'] : null;

        return match ($view) {
            'night', 'day' => $this->paginateByCaptureMethods(
                config("fish_options.list_views.$view.capture_methods"),
                $perPage,
                $lastId
            ),
            'alpha' => $this->paginateAlpha($filters, $perPage),
            'same_name' => $this->paginateSameName($filters, $perPage),
            default => null,
        };
    }

    private function paginateByCaptureMethods(array $methods, int $perPage, ?int $lastId): array
    {
        $query = $this->listQuery()
            ->whereHas('captureRecords', fn ($q) => $this->whereCaptureMethodIn($q, $methods))
            ->orderByDesc('id');

        return $this->pageByIdCursor($query, $perPage, $lastId);
    }

    private function countByCaptureMethods(array $methods): int
    {
        return Fish::query()
            ->whereHas('captureRecords', fn ($q) => $this->whereCaptureMethodIn($q, $methods))
            ->count();
    }

    /** 捕獲方式精確比對：去前後空白、不分大小寫、整個值相等 */
    private function whereCaptureMethodIn($query, array $methods): void
    {
        $query->whereIn(DB::raw('LOWER(TRIM(capture_method))'), array_map('mb_strtolower', $methods));
    }

    private function paginateAlpha(array $filters, int $perPage): array
    {
        $index = $this->nameIndex();
        $rows = $this->afterNameCursor($index, $filters);
        $page = $rows->take($perPage)->values();
        $items = $this->itemsInOrder($page->pluck('id')->all(), $page->keyBy('id'), 'segment');
        // 段標題的魚數只在第一頁給，往下載入不帶
        $groupCounts = isset($filters['last_id']) ? null : $this->segmentCounts($index);

        return $this->namePage($items, $rows->count() > $perPage, $groupCounts);
    }

    private function paginateSameName(array $filters, int $perPage): array
    {
        $groups = $this->sameNameGroups($this->nameIndex());
        $cursor = $this->nameCursor($filters);
        if ($cursor !== null) {
            $groups = $groups->filter(fn ($group) => $this->compareNameRows($group[0], $cursor, false) > 0);
        }

        $pageRows = collect();
        $groupLabels = [];
        foreach ($groups->values() as $index => $group) {
            if ($pageRows->count() >= $perPage) {
                break;
            }
            foreach ($group as $row) {
                $pageRows->push($row);
                $groupLabels[$row['id']] = ['label' => trim($group[0]['name']), 'count' => count($group)];
            }
            $lastIndex = $index;
        }
        $hasMore = isset($lastIndex) && $groups->count() > $lastIndex + 1;
        $items = $this->itemsInOrder($pageRows->pluck('id')->all(), $groupLabels, 'same_name');

        return $this->namePage($items, $hasMore, null);
    }

    /** 依名字排序的看法共用的回傳格式；游標為最後一條魚的編號與名字 */
    private function namePage(array $items, bool $hasMore, ?array $groupCounts): array
    {
        $last = $hasMore && $items !== [] ? end($items) : null;
        $page = [
            'items' => $items,
            'pageInfo' => [
                'hasMore' => $hasMore,
                'nextCursor' => $last['id'] ?? null,
                'nextName' => $last['name'] ?? null,
            ],
        ];
        if ($groupCounts !== null) {
            $page['groupCounts'] = $groupCounts;
        }

        return $page;
    }

    /** 依給定順序載入魚卡資料，並附上段標題（字母段或名字一樣的組） */
    private function itemsInOrder(array $ids, $labels, string $mode): array
    {
        $fishById = $this->listQuery()->whereIn('id', $ids)->get()->keyBy('id');
        $items = [];
        foreach ($ids as $id) {
            $item = $this->toListItem($fishById[$id]);
            if ($mode === 'same_name') {
                $item['group_label'] = $labels[$id]['label'];
                $item['group_count'] = $labels[$id]['count'];
            } else {
                $item['group_label'] = $labels[$id]['segment'];
            }
            $items[] = $item;
        }

        return $items;
    }

    /** 全部魚的編號與名字，依字母順序排好：段（A 到 Z、其他）、名字鍵、編號 */
    private function nameIndex(): Collection
    {
        return Fish::query()->orderBy('id')->get(['id', 'name'])
            ->map(fn (Fish $fish) => $this->nameRow($fish->id, (string) $fish->name))
            ->sort(fn ($a, $b) => $this->compareNameRows($a, $b))
            ->values();
    }

    private function nameRow(int $id, string $name): array
    {
        $key = mb_strtolower(trim($name));
        $segment = preg_match('/^[a-z]/', $key) ? strtoupper($key[0]) : self::OTHER_SEGMENT;

        return ['id' => $id, 'name' => $name, 'key' => $key, 'segment' => $segment];
    }

    /** 排序與游標共用的比較：段、名字鍵（位元組順序）、編號 */
    private function compareNameRows(array $a, array $b, bool $byId = true): int
    {
        $rank = fn (array $row) => $row['segment'] === self::OTHER_SEGMENT ? 1 : 0;

        return ($rank($a) <=> $rank($b))
            ?: ($a['segment'] <=> $b['segment'])
            ?: strcmp($a['key'], $b['key'])
            ?: ($byId ? $a['id'] <=> $b['id'] : 0);
    }

    private function nameCursor(array $filters): ?array
    {
        if (! isset($filters['last_id'])) {
            return null;
        }

        return $this->nameRow((int) $filters['last_id'], (string) ($filters['last_name'] ?? ''));
    }

    private function afterNameCursor(Collection $index, array $filters): Collection
    {
        $cursor = $this->nameCursor($filters);

        return $cursor === null
            ? $index
            : $index->filter(fn ($row) => $this->compareNameRows($row, $cursor) > 0)->values();
    }

    /** 名字鍵相同且至少兩條的魚，依名字鍵分組；名字空白不算 */
    private function sameNameGroups(Collection $index): Collection
    {
        return $index->filter(fn ($row) => $row['key'] !== '')
            ->groupBy('key')
            ->filter(fn ($group) => $group->count() > 1)
            ->map(fn ($group) => $group->sortBy('id')->values()->all())
            ->values();
    }

    /** 各字母段的魚數，依段順序 */
    private function segmentCounts(Collection $index): array
    {
        return $index->countBy('segment')
            ->map(fn ($count, $segment) => ['label' => (string) $segment, 'count' => $count])
            ->values()
            ->all();
    }

    private function listQuery(): Builder
    {
        // 基底查詢：僅主表避免不必要 eager（FR-009）
        // 重要：需選出 image 欄位，否則模型 accessor 無法判斷是否有自訂圖片，會一律回傳預設圖
        // 同時帶出 has_webp（若資料表有此欄位），可讓 accessor 選擇 webp
        // 加入 display_capture_record_id 以支援圖鑑主圖選擇功能
        $selects = ['id', 'name', 'image'];
        if (Schema::hasColumn('fish', 'has_webp')) {
            $selects[] = 'has_webp';
        }
        // 加入音檔欄位以支援清單頁音檔播放
        if (Schema::hasColumn('fish', 'audio_filename')) {
            $selects[] = 'audio_filename';
        }
        // 加入圖鑑主圖選擇欄位
        if (Schema::hasColumn('fish', 'display_capture_record_id')) {
            $selects[] = 'display_capture_record_id';
        }

        return Fish::query()
            ->select($selects)
            ->with([
                'displayCaptureRecord:id,image_path,image_focus_x,image_focus_y,image_zoom,image_rotation', // 預載圖鑑主圖關聯
            ]);
    }

    /** 編號新到舊的游標分頁（FR-005）：套游標、多取一筆判斷有沒有下一頁、映射精簡欄位 */
    private function pageByIdCursor(Builder $query, int $perPage, ?int $lastId): array
    {
        if (! is_null($lastId)) {
            // FR-005 游標邏輯 id < last_id
            $query->where('id', '<', (int) $lastId); // 游標條件（FR-005）
        }

        $lookahead = config('fish_search.lookahead_enabled');
        $limit = $perPage + ($lookahead ? 1 : 0);
        $rows = $query->limit($limit)->get();

        $hasMore = false;
        if ($lookahead && $rows->count() > $perPage) {
            $hasMore = true;
            $rows = $rows->slice(0, $perPage)->values();
        }

        $items = $rows->map(fn (Fish $f) => $this->toListItem($f))->all();

        $nextCursor = ($hasMore && ! empty($items)) ? end($items)['id'] : null;

        return [
            'items' => $items,
            'pageInfo' => [
                'hasMore' => $hasMore,
                'nextCursor' => $nextCursor,
            ],
        ];
    }

    /** 映射精簡欄位（模型 accessor 提供 image_url、display_image_url 和 audio_url） */
    private function toListItem(Fish $f): array
    {
        return [
            'id' => $f->id,
            'name' => $f->name,
            'image_url' => $f->image_url,
            'display_image_url' => $f->display_image_url, // 優先使用圖鑑主圖
            'display_image_framing' => $f->display_image_framing,
            'audio_url' => $f->audio_url, // 透過模型 accessor 轉換為完整播放連結
        ];
    }

    public function getLatestAt(): ?int
    {
        $latest = Fish::withTrashed()->max('updated_at');
        if ($latest === null) {
            return null;
        }

        return (int) \Carbon\Carbon::parse($latest)->getPreciseTimestamp(3);
    }
}
