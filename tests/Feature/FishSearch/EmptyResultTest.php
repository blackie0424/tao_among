<?php

namespace Tests\Feature\FishSearch;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Fish;

uses(RefreshDatabase::class); // Pest 測試自動 migrate，確保資料表存在

it('loads initial items correctly and sets hasMore to false when count equals perPage', function () {
    // Arrange: 建立 5 筆資料
    Fish::factory()->count(5)->create();

    // Act: 初次載入 perPage=5
    $response = $this->get('/fishs?perPage=5');

    // Assert: 驗證狀態碼、筆數和游標資訊
    $response->assertStatus(200)
        ->assertInertia(function ($page) {
            $items = $page->toArray()['props']['items'] ?? [];
            // 使用 expect() 進行 Pest 風格的斷言
            expect($items)->toHaveCount(5);
            
            $page->where('pageInfo.hasMore', false)
                 ->where('pageInfo.nextCursor', null); // 當筆數剛好等於 perPage 且無更多資料時
        });
});

it('empty result when past tail', function () {
    // 建立 5 筆資料，確保 id 遞減排序
    Fish::factory()->count(5)->create();

    // 初次載入 perPage=5 取得 5 筆，hasMore=false
    $first = $this->get('/fishs?perPage=5');
    $first->assertStatus(200)
        ->assertInertia(function ($page) use (&$nextCursor) {
            $items = $page->toArray()['props']['items'] ?? [];
            $this->assertCount(5, $items);
            $page->where('pageInfo.hasMore', false)
                 ->where('pageInfo.nextCursor', null);
        });

    // 以最小 id - 1 作為 next 游標（手動模擬超尾端）
    $all = Fish::orderByDesc('id')->pluck('id')->all();
    $minId = min($all);
    $resp2 = $this->get('/fishs?last_id=' . ($minId - 1));
    $resp2->assertStatus(200)
        ->assertInertia(fn ($page) => $page->where('items', [])
            ->where('pageInfo.hasMore', false)
            ->where('pageInfo.nextCursor', null));

});
