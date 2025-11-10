<?php

namespace Tests\Feature\FishSearch;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Fish;

class PerPageNormalizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * FR-007 perPage 正規化: 超過最大值或無效值回退至 default=20
     */
    public function test_per_page_out_of_range_falls_back_to_default(): void
    {
        // 建立 30 筆資料
        Fish::factory()->count(30)->create();

        // perPage=999 應回退至 20
        $response = $this->get('/fishs?perPage=999');
        $response->assertStatus(200)
            ->assertInertia(fn($page) => $page->where('items', fn($items) => count($items) === 20)
                ->where('pageInfo.hasMore', true));
    }

    public function test_per_page_invalid_string_falls_back_to_default(): void
    {
        Fish::factory()->count(25)->create();
        $response = $this->get('/fishs?perPage=abc');
        $response->assertStatus(200)
            ->assertInertia(fn($page) => $page->where('items', fn($items) => count($items) === 20)
                ->where('pageInfo.hasMore', true));
    }
}
