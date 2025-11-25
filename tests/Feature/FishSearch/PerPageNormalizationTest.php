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

it('check string to number', function () {
    $this->assertEquals(50, (int) '50');
    $this->assertEquals(0, (int) 'abc');
});

it('get default perPage value when perPage is 0', function () {
    $filters = ['perPage' => '0'];
    $perPage = (int)($filters['perPage'] ?? config('fish_search.per_page_default'));
    $perPage = max($perPage, config('fish_search.per_page_default'));
    $this->assertEquals(50, $perPage);
});


it('test per page invalid string falls back to default', function () {
    $defaultPerPage = config('fish_search.per_page_default');
    // 建立 defaultPerPage+10 筆資料，表示現有資料量大於查詢筆數
    Fish::factory()->count($defaultPerPage + 10)->create();

    $this->get('/fishs?perPage=abc')
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page->where('items', fn ($items) => count($items) === $defaultPerPage)
            ->where('pageInfo.hasMore', true));
});
