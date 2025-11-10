<?php

namespace Tests\Unit\Requests;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FishSearchRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * perPage 邊界正規化
     */
    public function test_per_page_normalization_boundaries(): void
    {
        // 未提供 → 20
        $resp1 = $this->get('/fishs');
        $resp1->assertStatus(200)
            ->assertInertia(fn($page) => $page->where('items', fn($items) => count($items) === 0));

        // 提供 0 → 20
        $resp2 = $this->get('/fishs?perPage=0');
        $resp2->assertStatus(200)
            ->assertInertia(fn($page) => $page->where('items', fn($items) => count($items) === 0));

        // 提供 51 → 20
        $resp3 = $this->get('/fishs?perPage=51');
        $resp3->assertStatus(200)
            ->assertInertia(fn($page) => $page->where('items', fn($items) => count($items) === 0));

        // 提供字串 → 20
        $resp4 = $this->get('/fishs?perPage=abc');
        $resp4->assertStatus(200)
            ->assertInertia(fn($page) => $page->where('items', fn($items) => count($items) === 0));

        // 提供 10 → 10（無資料仍為 0）
        $resp5 = $this->get('/fishs?perPage=10');
        $resp5->assertStatus(200)
            ->assertInertia(fn($page) => $page->where('items', fn($items) => count($items) === 0));
    }
}
