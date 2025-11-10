<?php

namespace Tests\Feature\FishSearch;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Fish;

class EmptyResultTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 空結果：滑到尾端後再請求，應 items=[]、hasMore=false、nextCursor=null
     */
    public function test_empty_result_when_past_tail(): void
    {
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
            ->assertInertia(fn($page) => $page->where('items', [])
                ->where('pageInfo.hasMore', false)
                ->where('pageInfo.nextCursor', null));
    }
}
