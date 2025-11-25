<?php

namespace Tests\Feature\FishSearch;

use Tests\TestCase;
use App\Models\Fish;

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
     * FR-007 perPage 正規化: 超過最大值或無效值回退至 default=50
     */

it('per page out of range falls back to default', function () {
    $defaultPerPage = config('fish_search.per_page_default');
    // 建立 defaultPerPage+10 筆資料，表示現有資料量大於查詢筆數
    Fish::factory()->count($defaultPerPage + 10)->create();

    // perPage=999 應回退至 $defaultPerPage
    $this->get('/fishs?perPage=999')
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page->where('items', fn ($items) => count($items) === $defaultPerPage)
            ->where('pageInfo.hasMore', true));
});

it('test per page invalid string falls back to default', function () {
    // 建立 30 筆資料
    Fish::factory()->count(30)->create();

    // perPage=999 應回退至 20
    $this->get('/fishs?perPage=abc')
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page->where('items', fn ($items) => count($items) === 20)
            ->where('pageInfo.hasMore', true));
});
