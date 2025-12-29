<?php

namespace App\Services;

use App\Models\Fish;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * FishSearchService — 後端搜尋核心服務
 * Trace: FR-001 多條件後端搜尋, FR-002 精簡欄位, FR-003 比對規則, FR-005 游標分頁,
 *        FR-007 perPage 正規化（搭配 Request）, FR-009 降低關聯載入, SC-004 payload 降幅
 */
class FishSearchService
{
    protected $fishService;

    public function __construct(FishService $fishService)
    {
        $this->fishService = $fishService;
    }
    /**
     * 執行魚類搜尋
     */
    public function search(array $filters)
    {
        $query = Fish::with(['size', 'tribalClassifications', 'captureRecords'])
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
        if (!empty($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }
    }

    /**
     * 套用部落相關篩選
     */
    protected function applyTribalFilters(Builder $query, array $filters)
    {
        $tribalFilters = array_filter([
            'tribe' => $filters['tribe'] ?? null,
            'food_category' => $filters['dietary_classification'] ?? null, // 映射到正確的資料庫欄位
            'processing_method' => $filters['processing_method'] ?? null,
        ]);

        if (!empty($tribalFilters)) {
            $query->whereHas('tribalClassifications', function ($q) use ($tribalFilters) {
                foreach ($tribalFilters as $field => $value) {
                    if ($field === 'processing_method') {
                        $q->where($field, 'like', '%' . $value . '%');
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

        if (!empty($captureFilters)) {
            $query->whereHas('captureRecords', function ($q) use ($captureFilters) {
                if (!empty($captureFilters['capture_location'])) {
                    $q->where('location', 'like', '%' . $captureFilters['capture_location'] . '%');
                }
                if (!empty($captureFilters['capture_method'])) {
                    $q->where('capture_method', 'like', '%' . $captureFilters['capture_method'] . '%');
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
            'tribes' => ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley'],
            'dietaryClassifications' => ['oyod', 'rahet', '不分類', '不食用', '?', ''],
            'processingMethods' => $this->getUniqueProcessingMethods(),
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
        $query = Fish::with(['size', 'tribalClassifications', 'captureRecords']);

        $this->applyNameFilter($query, $filters);
        $this->applyTribalFilters($query, $filters);
        $this->applyCaptureFilters($query, $filters);

        return $query;
    }

    /**
     * 取得搜尋結果統計
     */
    public function getSearchStats(array $filters)
    {
        $query = $this->buildSearchQuery($filters);
        
        return [
            'total_results' => $query->count(),
            'tribes_covered' => $query->whereHas('tribalClassifications')->distinct('id')->count(),
            'with_capture_records' => $query->whereHas('captureRecords')->count(),
        ];
    }

    /**
     * 游標式分頁 + 精簡欄位（FR-001, FR-002, FR-005, FR-007, FR-009, SC-004）
     * @param array $filters cleaned filters (FishSearchRequest::cleaned)
     * @return array{items: array<int, array{id:int,name:string,image_url:string}>, pageInfo: array{hasMore:bool,nextCursor:int|null}}
     */
    public function paginate(array $filters): array
    {
        // Trace: FR-002 精簡欄位, FR-005 游標分頁, FR-007 正規化後 perPage 已由 Request 處理, SC-004 payload 降幅
        $perPage = (int)($filters['perPage'] ?? config('fish_search.per_page_default'));
        $lastId = $filters['last_id'] ?? null;

        // 基底查詢：僅主表避免不必要 eager（FR-009）
        // 重要：需選出 image 欄位，否則模型 accessor 無法判斷是否有自訂圖片，會一律回傳預設圖
        // 同時帶出 has_webp（若資料表有此欄位），可讓 accessor 選擇 webp
        // 加入 display_capture_record_id 以支援圖鑑主圖選擇功能
        $selects = ['id','name','image'];
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
        $query = Fish::query()
            ->select($selects)
            ->with([
                'tribalClassifications:id,fish_id,tribe,food_category',
                'displayCaptureRecord:id,image_path' // 預載圖鑑主圖關聯（僅需 id 和 image_path）
            ])
            ->orderByDesc('id');

        // 模糊/等值條件（FR-003）
        if (!empty($filters['name'])) {
            // FR-003 名稱模糊（大小寫不敏感）— 使用 LOWER + LIKE 以支援 SQLite/PG
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($filters['name']) . '%']);
        }
        if (!empty($filters['tribe']) || !empty($filters['food_category']) || !empty($filters['processing_method'])) {
            $tribe = $filters['tribe'] ?? null;
            $food = $filters['food_category'] ?? null;
            $proc = $filters['processing_method'] ?? null;
            $query->whereHas('tribalClassifications', function ($q) use ($tribe, $food, $proc) {
                if (!empty($tribe)) {
                    // FR-003 tribe 等值（LOWER=LOWER）
                    $q->whereRaw('LOWER(tribe) = LOWER(?)', [$tribe]);
                }
                if (!empty($food)) {
                    // FR-003 food_category 等值（LOWER=LOWER）
                    $q->whereRaw('LOWER(food_category) = LOWER(?)', [$food]);
                }
                if (!empty($proc)) {
                    // FR-003 processing_method 模糊大小寫不敏感
                    $q->whereRaw('LOWER(processing_method) LIKE ?', ['%' . strtolower($proc) . '%']);
                }
            });
        }
        if (!empty($filters['capture_location']) || !empty($filters['capture_method'])) {
            $loc = $filters['capture_location'] ?? null;
            $met = $filters['capture_method'] ?? null;
            $query->whereHas('captureRecords', function ($q) use ($loc, $met) {
                if (!empty($loc)) {
                    // FR-003 capture_location 模糊大小寫不敏感
                    $q->whereRaw('LOWER(location) LIKE ?', ['%' . strtolower($loc) . '%']);
                }
                if (!empty($met)) {
                    // FR-003 capture_method 模糊大小寫不敏感
                    $q->whereRaw('LOWER(capture_method) LIKE ?', ['%' . strtolower($met) . '%']);
                }
            });
        }
        if (!is_null($lastId)) {
            // FR-005 游標邏輯 id < last_id
            $query->where('id', '<', (int)$lastId); // 游標條件（FR-005）
        }

        $lookahead = config('fish_search.lookahead_enabled');
        $limit = $perPage + ($lookahead ? 1 : 0);
        $rows = $query->limit($limit)->get();

        $hasMore = false;
        if ($lookahead && $rows->count() > $perPage) {
            $hasMore = true;
            $rows = $rows->slice(0, $perPage)->values();
        }

        // 映射精簡欄位（模型 accessor 提供 image_url 和 audio_url）
        $items = $rows->map(function (Fish $f) {
            return [
                'id' => $f->id,
                'name' => $f->name,
                'image_url' => $f->image_url,
                'audio_url' => $f->audio_url, // 透過模型 accessor 轉換為完整播放連結
                'tribal_classifications' => $f->tribalClassifications->map(fn ($tc) => [
                    'tribe' => $tc->tribe,
                    'food_category' => $tc->food_category,
                ])->all(),
            ];
        })->all();

        $nextCursor = ($hasMore && !empty($items)) ? end($items)['id'] : null;

        return [
            'items' => $items,
            'pageInfo' => [
                'hasMore' => $hasMore,
                'nextCursor' => $nextCursor,
            ],
        ];
    }
}
