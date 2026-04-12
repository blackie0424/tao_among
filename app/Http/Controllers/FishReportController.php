<?php

namespace App\Http\Controllers;

use App\Contracts\FishStatisticsServiceInterface;
use Inertia\Inertia;
use Inertia\Response;

/**
 * FishReportController
 *
 * 單一責任：提供管理員量化報告頁面所需資料。
 * 遵循 SOLID 的依賴反轉原則（DIP），依賴 FishStatisticsServiceInterface
 * 而非具體實作，方便測試與未來擴展。
 */
class FishReportController extends Controller
{
    public function __construct(
        private FishStatisticsServiceInterface $statisticsService
    ) {
    }

    /**
     * 顯示魚類量化報告頁面。
     *
     * 資料結構：
     * - tribes: 所有部落清單（來自 config）
     * - foodCategories: 食用分類選項
     * - processingMethods: 處理方式選項
     * - statistics: 跨部落的統計矩陣資料
     */
    public function index(): Response
    {
        return Inertia::render('FishReport', [
            'tribes'            => config('fish_options.tribes', []),
            'foodCategories'    => config('fish_options.food_categories', []),
            'processingMethods' => config('fish_options.processing_methods', []),
            'statistics'        => $this->statisticsService->getStatistics(),
        ]);
    }
}
