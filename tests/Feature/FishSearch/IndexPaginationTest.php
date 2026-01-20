<?php

namespace Tests\Feature\FishSearch;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Fish;

uses(RefreshDatabase::class); // Pest 測試自動 migrate，確保資料表存在


it('一開始載入網頁時，可以取得預設數量資料', function () {
    $perPage = config('fish_search.per_page_default');

    // 建立資料：包含可模糊匹配的名稱
    Fish::factory()->count($perPage + 30)->create();
    // 初次載入
    $resp1 = $this->get('/fishs');
    $resp1->assertStatus(200)
        ->assertInertia(fn ($page) => $page->where('items', fn ($items) => count($items) === $perPage)
            ->where('pageInfo.hasMore', true));
});

it('魚類名稱只有部分相同時，也可以搜尋出來', function () {
    // 建立資料：包含可模糊匹配的名稱
    Fish::factory()->create(['name' => 'Blue Marlin','image' => 'blue-marlin.jpg','audio_filename' => 'blue-marlin.mp3']);
    Fish::factory()->create(['name' => 'Marbled Grouper','image' => 'marbled-grouper.jpg','audio_filename' => 'marbled-grouper.mp3']);
    Fish::factory()->create(['name' => 'Triggerfish','image' => 'triggerfish.jpg','audio_filename' => 'triggerfish.mp3']);
    Fish::factory()->count(30)->create();

    // 名稱模糊搜尋（大小寫不敏感）
    $resp = $this->get('/fishs?name=mar');
    $resp->assertStatus(200)
        ->assertInertia(function ($page) {
            $items = $page->toArray()['props']['items'] ?? [];
            $names = array_map(fn ($i) => $i['name'], $items);
            $this->assertTrue(collect($names)->every(fn ($n) => stripos($n, 'mar') !== false));
        });
});
