<?php

namespace Tests\Feature\FishSearch;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Fish;
use App\Models\TribalClassification;
use App\Models\CaptureRecord;

class MultiConditionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * US2: 多條件 AND 組合測試（名稱模糊 + tribe 等值 + processing_method 模糊 + capture_location 模糊）
     */
    public function test_multi_condition_and_filters(): void
    {
        // 目標魚：滿足所有條件
        $targetFish = Fish::factory()->create(['name' => 'Golden Snapper']);
        TribalClassification::factory()->forTribe('ivalino')->create([
            'fish_id' => $targetFish->id,
            'processing_method' => '去魚鱗',
            'food_category' => 'oyod',
        ]);
        CaptureRecord::factory()->create([
            'fish_id' => $targetFish->id,
            'location' => 'Deep Sea Ridge',
            'capture_method' => '網捕'
        ]);

        // 干擾魚：名稱符合但 tribe 不同
        $other1 = Fish::factory()->create(['name' => 'Golden Trout']);
        TribalClassification::factory()->forTribe('iranmeilek')->create(['fish_id' => $other1->id]);

        // 干擾魚：tribe 相同但名稱不符
        $other2 = Fish::factory()->create(['name' => 'Silver Eel']);
        TribalClassification::factory()->forTribe('ivalino')->create(['fish_id' => $other2->id]);

        // 干擾魚：名稱與 tribe 符合但 processing_method 不符
        $other3 = Fish::factory()->create(['name' => 'Golden Carp']);
        TribalClassification::factory()->forTribe('ivalino')->create([
            'fish_id' => $other3->id,
            'processing_method' => '剝皮'
        ]);

        // 發送多條件搜尋
        $response = $this->get('/fishs?name=gold&tribe=ivalino&processing_method=去魚鱗&capture_location=Ridge');
        $response->assertStatus(200)
            ->assertInertia(function ($page) use ($targetFish) {
                $items = $page->toArray()['props']['items'] ?? [];
                $names = array_map(fn($i) => $i['name'], $items);
                // 只應包含目標魚
                $this->assertEquals([$targetFish->name], $names);
                // pageInfo 結果對於單筆應 hasMore=false
                $page->where('pageInfo.hasMore', false);
            });
    }
}
