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

it('當last id被設為0，應該要收到422，表示資料錯誤', function () {
    $minId = 0;
    $response = $this->get('/fishs?last_id=' . $minId);
    $response->assertStatus(422);
    $response->assertJson([
        'error' => 'INVALID_CURSOR',
    ]);

});
