<?php

namespace Tests\Feature\FishSearch;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Fish;
use App\Models\TribalClassification;
use App\Models\CaptureRecord;

uses(RefreshDatabase::class); // Pest 測試自動 migrate，確保資料表存在


it('should filter out fish when the tribe condition does not match', function () {
    // 目標魚：滿足所有條件
    $targetFish = Fish::factory()->create(['name' => 'Golden Snapper']);
    
    // *** 修正點 1: 使用 Factory 允許的中文值 ***
    TribalClassification::factory()->forTribe('ivalino')->create([
        'fish_id' => $targetFish->id,
        'processing_method' => '?',
        'food_category' => 'oyod',
    ]);
    CaptureRecord::factory()->create([
        'fish_id' => $targetFish->id,
        'location' => 'beach',
        'capture_method' => 'mamasi'
    ]);

    // 干擾魚：名稱符合但 tribe 不同 (數據建立邏輯不變)
    $other1 = Fish::factory()->create(['name' => 'Golden Trout']);
    TribalClassification::factory()->forTribe('iranmeilek')->create(['fish_id' => $other1->id]);

    // 干擾魚：tribe 相同但名稱不符 (數據建立邏輯不變)
    $other2 = Fish::factory()->create(['name' => 'Silver Eel']);
    TribalClassification::factory()->forTribe('ivalino')->create(['fish_id' => $other2->id]);

    // 干擾魚：名稱與 tribe 符合但 foo_category 不符
    $other3 = Fish::factory()->create(['name' => 'Golden Carp']);
    TribalClassification::factory()->forTribe('ivalino')->create([
        'fish_id' => $other3->id,
        'food_category' => 'rahet'
    ]);

    // 發送多條件搜尋 (URL 參數必須與數據庫中的中文值匹配)
    // 注意：URL 查詢參數中的中文會被框架自動編碼和解碼
    $response = $this->get('/fishs?name=gold&tribe=ivalino&food_category=oyod&capture_location=beach');
    $response->assertStatus(200)
        ->assertInertia(function ($page) use ($targetFish) {
            $items = $page->toArray()['props']['items'] ?? [];
            $names = array_map(fn ($i) => $i['name'], $items);
            
            // 驗證結果
            $this->assertCount(1, $names);
            $this->assertEquals([$targetFish->name], $names);
            
            $page->where('pageInfo.hasMore', false);
        });
});
