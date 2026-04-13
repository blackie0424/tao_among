<?php

namespace App\Services;

use App\Models\CaptureRecord;
use App\Models\Fish;
use App\Models\FishAudio;
use App\Models\FishNote;
use App\Models\TribalClassification;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * DashboardService
 *
 * 負責提供 Dashboard 頁面所需的所有統計資料。
 * 依部落篩選（$tribe 為 null 表示全部模式）回傳不同維度的統計結果。
 */
class DashboardService
{
    /**
     * 取得部落清單。
     * 以 config/fish_options.php 的 tribes 為主清單，
     * 再補充資料庫中額外出現的部落值（去重）。
     */
    public function getTribes(): Collection
    {
        $knownTribes       = collect(config('fish_options.tribes', []));
        $tribesFromTribal  = TribalClassification::distinct()->whereNotNull('tribe')->pluck('tribe');
        $tribesFromCapture = CaptureRecord::distinct()->whereNotNull('tribe')->pluck('tribe');

        return $knownTribes
            ->merge($tribesFromTribal)
            ->merge($tribesFromCapture)
            ->unique()
            ->values();
    }

    /**
     * 取得魚類統計資料。
     *
     * @param  string|null $tribe  部落名稱；null 表示全部模式
     * @return array{total: int, with_capture_record: int, with_audio: int, with_tribal_classification: int}
     */
    public function getFishStats(?string $tribe): array
    {
        if ($tribe) {
            return [
                'total'                      => Fish::count(),
                'with_capture_record'        => Fish::whereHas('captureRecords', fn ($q) => $q->where('tribe', $tribe))->count(),
                'with_audio'                 => Fish::whereHas('audios', fn ($q) => $q->where('locate', $tribe))->count(),
                'with_tribal_classification' => Fish::whereHas('tribalClassifications', fn ($q) => $q->where('tribe', $tribe))->count(),
            ];
        }

        return [
            'total'                      => Fish::count(),
            'with_capture_record'        => Fish::has('captureRecords')->count(),
            'with_audio'                 => Fish::has('audios')->count(),
            'with_tribal_classification' => Fish::has('tribalClassifications')->count(),
        ];
    }

    /**
     * 取得捕獲紀錄統計資料。
     *
     * 全部模式：breakdown 為各部落分佈（by_tribe）。
     * 部落模式：breakdown 為各捕獲方式分佈（by_method）。
     *
     * @param  string|null $tribe
     * @return array{total: int, by_tribe: Collection, by_method: Collection}
     */
    public function getCaptureStats(?string $tribe): array
    {
        if ($tribe) {
            return [
                'total'    => CaptureRecord::where('tribe', $tribe)->count(),
                'by_tribe' => collect(),
                'by_method' => CaptureRecord::where('tribe', $tribe)
                    ->selectRaw('capture_method, COUNT(*) as count')
                    ->groupBy('capture_method')
                    ->orderByDesc('count')
                    ->get()
                    ->map(fn ($row) => ['label' => $row->capture_method ?: '未記錄', 'count' => $row->count])
                    ->values(),
            ];
        }

        return [
            'total'    => CaptureRecord::count(),
            'by_tribe' => CaptureRecord::selectRaw('tribe, COUNT(*) as count')
                ->groupBy('tribe')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($row) => ['tribe' => $row->tribe ?: '未分類', 'count' => $row->count])
                ->values(),
            'by_method' => collect(),
        ];
    }

    /**
     * 取得部落分類統計資料。
     *
     * 全部模式：breakdown 為各部落分佈（by_tribe）。
     * 部落模式：breakdown 為食用分類（by_food_category）及處理方法（by_processing_method），
     *           計算單位為魚種數（因 unique(fish_id, tribe) 約束，一魚一部落僅一筆）。
     *
     * @param  string|null $tribe
     * @return array{
     *   total: int,
     *   by_tribe: Collection,
     *   by_food_category: Collection,
     *   by_processing_method: Collection
     * }
     */
    public function getTribalStats(?string $tribe): array
    {
        if ($tribe) {
            $foodCategoryMap = TribalClassification::where('tribe', $tribe)
                ->selectRaw('food_category, COUNT(*) as count')
                ->groupBy('food_category')
                ->pluck('count', 'food_category')
                ->toArray();

            $processingMethodMap = TribalClassification::where('tribe', $tribe)
                ->selectRaw('processing_method, COUNT(*) as count')
                ->groupBy('processing_method')
                ->pluck('count', 'processing_method')
                ->toArray();

            $configFoodCategories    = config('fish_options.food_categories', []);
            $configProcessingMethods = config('fish_options.processing_methods', []);

            // 合併 config 定義的選項與實際資料（config 順序優先，補入 0 筆）
            $allFoodKeys    = array_unique(array_merge($configFoodCategories, array_keys($foodCategoryMap)));
            $allProcessKeys = array_unique(array_merge($configProcessingMethods, array_keys($processingMethodMap)));

            $byFoodCategory = collect($allFoodKeys)->map(fn ($cat) => [
                'label' => $cat ?: '未分類',
                'count' => $foodCategoryMap[$cat] ?? 0,
            ])->values();

            $byProcessingMethod = collect($allProcessKeys)->map(fn ($method) => [
                'label' => $method ?: '未記錄',
                'count' => $processingMethodMap[$method] ?? 0,
            ])->values();

            return [
                'total'                => TribalClassification::where('tribe', $tribe)->count(),
                'by_tribe'             => collect(),
                'by_food_category'     => $byFoodCategory,
                'by_processing_method' => $byProcessingMethod,
            ];
        }

        return [
            'total' => TribalClassification::count(),
            'by_tribe' => TribalClassification::selectRaw('tribe, COUNT(*) as count')
                ->groupBy('tribe')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($row) => ['tribe' => $row->tribe ?: '未分類', 'count' => $row->count])
                ->values(),
            'by_food_category'     => collect(),
            'by_processing_method' => collect(),
        ];
    }

    /**
     * 取得音檔統計資料。
     *
     * @param  string|null $tribe
     * @return array{total: int, by_locate: Collection}
     */
    public function getAudioStats(?string $tribe): array
    {
        if ($tribe) {
            return [
                'total'     => FishAudio::where('locate', $tribe)->count(),
                'by_locate' => collect(),
            ];
        }

        return [
            'total'     => FishAudio::count(),
            'by_locate' => FishAudio::selectRaw('locate, COUNT(*) as count')
                ->groupBy('locate')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($row) => ['locate' => $row->locate ?: '未分類', 'count' => $row->count])
                ->values(),
        ];
    }

    /**
     * 取得地方知識統計資料。
     *
     * @param  string|null $tribe
     * @return array{total: int, by_type: Collection}
     */
    public function getNoteStats(?string $tribe): array
    {
        $query = $tribe
            ? FishNote::where('locate', $tribe)
            : FishNote::query();

        return [
            'total'   => $query->count(),
            'by_type' => (clone $query)
                ->selectRaw('note_type, COUNT(*) as count')
                ->groupBy('note_type')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($row) => ['type' => $row->note_type ?: '未分類', 'count' => $row->count])
                ->values(),
        ];
    }

    /**
     * 取得 LINE 使用者統計資料（不受部落篩選影響）。
     *
     * @return array{total: int, by_role: Collection}
     */
    public function getUserStats(): array
    {
        return [
            'total'   => User::where('source', 'line')->count(),
            'by_role' => User::where('source', 'line')
                ->selectRaw('role, COUNT(*) as count')
                ->groupBy('role')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($row) => ['role' => $row->role ?: '無角色', 'count' => $row->count])
                ->values(),
        ];
    }
}
