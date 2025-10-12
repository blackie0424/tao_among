<?php

namespace App\Services;

use App\Models\Fish;
use Illuminate\Database\Eloquent\Builder;

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
        
        // 處理圖片 URL
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
}
