<?php

namespace App\Services;

use App\Contracts\FishStatisticsServiceInterface;
use App\Models\CaptureRecord;
use App\Models\Fish;
use App\Models\TribalClassification;

/**
 * FishStatisticsService
 *
 * 單一責任：負責聚合魚類資料的統計結果，供量化報告使用。
 * 遵循 SOLID 原則：
 *   - S: 只負責統計資料聚合
 *   - O: 新增統計維度無需修改現有邏輯
 *   - D: 實作 FishStatisticsServiceInterface，可被 mock 替換
 */
class FishStatisticsService implements FishStatisticsServiceInterface
{
    /**
     * 取得所有魚類統計資料。
     */
    public function getStatistics(): array
    {
        return [
            'total_fish'               => Fish::count(),
            'food_categories_by_tribe' => $this->getFoodCategoriesByTribe(),
            'capture_methods_by_tribe' => $this->getCaptureMethodsByTribe(),
            'processing_methods'       => $this->getProcessingMethods(),
        ];
    }

    /**
     * 依部落統計食用分類數量。
     *
     * @return array<string, array<string, int>>  [部落名稱 => [食用分類 => 數量]]
     */
    private function getFoodCategoriesByTribe(): array
    {
        $result = [];

        TribalClassification::selectRaw('tribe, food_category, COUNT(*) as count')
            ->groupBy('tribe', 'food_category')
            ->get()
            ->each(function ($row) use (&$result) {
                $result[$row->tribe][$row->food_category] = (int) $row->count;
            });

        return $result;
    }

    /**
     * 依部落統計捕獲方式數量。
     *
     * @return array<string, array<string, int>>  [部落名稱 => [捕獲方式 => 數量]]
     */
    private function getCaptureMethodsByTribe(): array
    {
        $result = [];

        CaptureRecord::selectRaw('tribe, capture_method, COUNT(*) as count')
            ->groupBy('tribe', 'capture_method')
            ->get()
            ->each(function ($row) use (&$result) {
                $result[$row->tribe][$row->capture_method] = (int) $row->count;
            });

        return $result;
    }

    /**
     * 統計各處理方式的總數量（跨部落）。
     *
     * @return array<string, int>  [處理方式 => 數量]
     */
    private function getProcessingMethods(): array
    {
        $result = [];

        TribalClassification::selectRaw('processing_method, COUNT(*) as count')
            ->groupBy('processing_method')
            ->get()
            ->each(function ($row) use (&$result) {
                $result[$row->processing_method] = (int) $row->count;
            });

        return $result;
    }
}
