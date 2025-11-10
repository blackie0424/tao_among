<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Fish;
use App\Services\FishSearchService;
use App\Services\FishService; // 依賴注入簡化

class FishSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Lookahead: perPage=5, 建立 6 筆資料 → hasMore=true, 只回 5 筆, nextCursor=最後一筆 id
     */
    public function test_paginate_lookahead_has_more(): void
    {
        Fish::factory()->count(6)->create();
        $service = new FishSearchService(app(FishService::class));
        $result = $service->paginate(['perPage' => 5]);
        $this->assertCount(5, $result['items']);
        $this->assertTrue($result['pageInfo']['hasMore']);
        $this->assertNotNull($result['pageInfo']['nextCursor']);
        $lastReturnedId = end($result['items'])['id'];
        $this->assertEquals($result['pageInfo']['nextCursor'], $lastReturnedId);
    }
}
