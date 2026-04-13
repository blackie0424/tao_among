<?php

namespace Database\Seeders;

use App\Models\Fish;
use App\Models\TribalClassification;
use Illuminate\Database\Seeder;

/**
 * 展示用假資料：30 筆魚類 + iraraley 部落分類
 *
 * 完成度設計（便於觀察紅綠燈效果）：
 *   - 26 筆有 iraraley 分類，4 筆未記錄
 *   - 食用分類：5 筆為 '?'  → 缺漏 4+5=9  → 完成 21/30 = 70% 🟡 黃燈
 *   - 處理方式：2 筆為 '?'  → 缺漏 4+2=6  → 完成 24/30 = 80% 🟢 綠燈（臨界）
 */
class DashboardDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 建立 30 筆魚類
        $fishes = Fish::factory()->count(30)->create();

        $rows = [];

        // 前 5 筆：食用分類未確認（?），處理方式正常
        foreach ($fishes->take(5) as $fish) {
            $rows[] = [
                'fish_id'            => $fish->id,
                'tribe'              => 'iraraley',
                'food_category'      => '?',
                'processing_method'  => '去魚鱗',
                'notes'              => null,
                'created_at'         => now(),
                'updated_at'         => now(),
            ];
        }

        // 第 6-7 筆：處理方式未確認（?），食用分類正常
        foreach ($fishes->slice(5, 2) as $fish) {
            $rows[] = [
                'fish_id'            => $fish->id,
                'tribe'              => 'iraraley',
                'food_category'      => 'oyod',
                'processing_method'  => '?',
                'notes'              => null,
                'created_at'         => now(),
                'updated_at'         => now(),
            ];
        }

        // 第 8-26 筆（19 筆）：完整資料
        $foodOptions = ['oyod', 'oyod', 'oyod', 'rahet', 'rahet', '不分類', '不食用'];
        $procOptions = ['去魚鱗', '去魚鱗', '不去魚鱗', '不去魚鱗', '剝皮', '不食用'];

        foreach ($fishes->slice(7, 19) as $index => $fish) {
            $rows[] = [
                'fish_id'            => $fish->id,
                'tribe'              => 'iraraley',
                'food_category'      => $foodOptions[$index % count($foodOptions)],
                'processing_method'  => $procOptions[$index % count($procOptions)],
                'notes'              => null,
                'created_at'         => now(),
                'updated_at'         => now(),
            ];
        }

        // 最後 4 筆魚類（slice(26)）無任何分類紀錄 → 未記錄

        TribalClassification::insert($rows);

        $this->command->info('DashboardDemoSeeder 完成：30 筆魚類，iraraley 分類 26 筆（食用 70% 🟡，處理 80% 🟢）');
    }
}
