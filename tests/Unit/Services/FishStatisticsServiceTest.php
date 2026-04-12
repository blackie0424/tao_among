<?php

use Tests\TestCase;
use App\Models\Fish;
use App\Models\TribalClassification;
use App\Models\CaptureRecord;
use App\Services\FishStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

it('returns total fish count', function () {
    Fish::factory()->count(5)->create();
    Fish::factory()->count(3)->create(); // 總共8條魚

    $service = new FishStatisticsService();
    $stats = $service->getStatistics();

    expect($stats['total_fish'])->toBe(8);
});

it('returns food category statistics by tribe', function () {
    $fish1 = Fish::factory()->create();
    $fish2 = Fish::factory()->create();
    $fish3 = Fish::factory()->create(); // 第 3 隻魚，避免 unique(fish_id, tribe) 衝突

    // ivalino部落：2個oyod（fish1, fish2）, 1個rahet（fish3）
    // 每筆 (fish_id, tribe) 組合唯一，符合資料庫約束
    TribalClassification::factory()->forTribe('ivalino')->withFoodCategory('oyod')->create(['fish_id' => $fish1->id]);
    TribalClassification::factory()->forTribe('ivalino')->withFoodCategory('oyod')->create(['fish_id' => $fish2->id]);
    TribalClassification::factory()->forTribe('ivalino')->withFoodCategory('rahet')->create(['fish_id' => $fish3->id]);

    // iranmeilek部落：1個不食用
    TribalClassification::factory()->forTribe('iranmeilek')->withFoodCategory('不食用')->create(['fish_id' => $fish1->id]);

    $service = new FishStatisticsService();
    $stats = $service->getStatistics();

    expect($stats['food_categories_by_tribe']['ivalino']['oyod'])->toBe(2);
    expect($stats['food_categories_by_tribe']['ivalino']['rahet'])->toBe(1);
    expect($stats['food_categories_by_tribe']['iranmeilek']['不食用'])->toBe(1);
});

it('returns capture method statistics by tribe', function () {
    $fish1 = Fish::factory()->create();
    $fish2 = Fish::factory()->create();

    // ivalino部落：2個網捕, 1個釣魚
    CaptureRecord::factory()->forTribe('ivalino')->create([
        'fish_id' => $fish1->id,
        'capture_method' => '網捕'
    ]);
    CaptureRecord::factory()->forTribe('ivalino')->create([
        'fish_id' => $fish2->id,
        'capture_method' => '網捕'
    ]);
    CaptureRecord::factory()->forTribe('ivalino')->create([
        'fish_id' => $fish1->id,
        'capture_method' => '釣魚'
    ]);

    // iranmeilek部落：1個魚叉
    CaptureRecord::factory()->forTribe('iranmeilek')->create([
        'fish_id' => $fish1->id,
        'capture_method' => '魚叉'
    ]);

    $service = new FishStatisticsService();
    $stats = $service->getStatistics();

    expect($stats['capture_methods_by_tribe']['ivalino']['網捕'])->toBe(2);
    expect($stats['capture_methods_by_tribe']['ivalino']['釣魚'])->toBe(1);
    expect($stats['capture_methods_by_tribe']['iranmeilek']['魚叉'])->toBe(1);
});

it('returns processing method statistics', function () {
    $fish1 = Fish::factory()->create();
    $fish2 = Fish::factory()->create();
    $fish3 = Fish::factory()->create(); // 第 3 隻魚，避免 unique(fish_id, tribe) 衝突

    // 去魚鱗：3個 — 每筆分配不同的 (fish_id, tribe) 組合
    TribalClassification::factory()->forTribe('ivalino')->create(['fish_id' => $fish1->id, 'processing_method' => '去魚鱗']);
    TribalClassification::factory()->forTribe('ivalino')->create(['fish_id' => $fish2->id, 'processing_method' => '去魚鱗']);
    TribalClassification::factory()->forTribe('ivalino')->create(['fish_id' => $fish3->id, 'processing_method' => '去魚鱗']);

    // 剝皮：2個 — 使用 iranmeilek 部落，避免與前三筆衝突
    TribalClassification::factory()->forTribe('iranmeilek')->create(['fish_id' => $fish1->id, 'processing_method' => '剝皮']);
    TribalClassification::factory()->forTribe('iranmeilek')->create(['fish_id' => $fish2->id, 'processing_method' => '剝皮']);

    // 不食用：1個 — 使用 imowrod 部落，確保唯一性
    TribalClassification::factory()->forTribe('imowrod')->create(['fish_id' => $fish1->id, 'processing_method' => '不食用']);

    $service = new FishStatisticsService();
    $stats = $service->getStatistics();

    expect($stats['processing_methods']['去魚鱗'])->toBe(3);
    expect($stats['processing_methods']['剝皮'])->toBe(2);
    expect($stats['processing_methods']['不食用'])->toBe(1);
});

it('excludes soft deleted records from statistics', function () {
    $fish = Fish::factory()->create();

    // 建立一個正常的部落分類（明確指定 ivalino 部落）
    TribalClassification::factory()->forTribe('ivalino')->create([
        'fish_id' => $fish->id,
        'food_category' => 'oyod',
    ]);

    // 建立一個已軟刪除的部落分類（iranmeilek 部落，避免 unique(fish_id, tribe) 衝突）
    $deletedClassification = TribalClassification::factory()->forTribe('iranmeilek')->create([
        'fish_id' => $fish->id,
        'food_category' => 'rahet',
    ]);
    $deletedClassification->delete();

    $service = new FishStatisticsService();
    $stats = $service->getStatistics();

    expect($stats['food_categories_by_tribe'])->toHaveKey('ivalino');           // 正常筆數應出現
    expect($stats['food_categories_by_tribe'])->not->toHaveKey('iranmeilek');   // 軟刪除後不應出現
    expect($stats['total_fish'])->toBe(1); // 只有一條魚
});

it('returns empty statistics when no data exists', function () {
    $service = new FishStatisticsService();
    $stats = $service->getStatistics();

    expect($stats['total_fish'])->toBe(0);
    expect($stats['food_categories_by_tribe'])->toBeEmpty();
    expect($stats['capture_methods_by_tribe'])->toBeEmpty();
    expect($stats['processing_methods'])->toBeEmpty();
});