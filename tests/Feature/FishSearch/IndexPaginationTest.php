<?php

namespace Tests\Feature\FishSearch;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Fish;

class IndexPaginationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 初次載入 + 名稱模糊搜尋
     */
    public function test_initial_load_and_name_search(): void
    {
        // 建立資料：包含可模糊匹配的名稱
        Fish::factory()->create(['name' => 'Blue Marlin']);
        Fish::factory()->create(['name' => 'Marbled Grouper']);
        Fish::factory()->create(['name' => 'Triggerfish']);
        Fish::factory()->count(30)->create();

        // 初次載入（默認 20 筆，hasMore=true）
        $resp1 = $this->get('/fishs');
        $resp1->assertStatus(200)
            ->assertInertia(fn($page) => $page->where('items', fn($items) => count($items) === 20)
                ->where('pageInfo.hasMore', true));

        // 名稱模糊搜尋（大小寫不敏感）
        $resp2 = $this->get('/fishs?name=mar');
        $resp2->assertStatus(200)
            ->assertInertia(function ($page) {
                $items = $page->toArray()['props']['items'] ?? [];
                $names = array_map(fn($i) => $i['name'], $items);
                $this->assertTrue(collect($names)->every(fn($n) => stripos($n, 'mar') !== false));
            });
    }
}
