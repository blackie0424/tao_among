<?php

use App\Models\Fish;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

uses(RefreshDatabase::class);

it('can get fish list by time condition', function () {

    //條件時間
    $date = "2025/03/07";
    $since = strtotime($date);

    // 測試資料
    $fishs = Fish::factory()->count(3)->sequence(
        ['created_at' => \Carbon\Carbon::createFromTimestamp(strtotime('-1 day', $since)), 'updated_at' => \Carbon\Carbon::createFromTimestamp(strtotime('-1 day', $since))],
        ['created_at' => \Carbon\Carbon::createFromTimestamp(strtotime('+1 day', $since)), 'updated_at' => \Carbon\Carbon::createFromTimestamp(strtotime('+1 day', $since))],
        ['created_at' => \Carbon\Carbon::createFromTimestamp(strtotime('+2 days', $since)), 'updated_at' => \Carbon\Carbon::createFromTimestamp(strtotime('+2 days', $since))]
    )->create();

    $expectedFishs = $fishs->filter(function ($fish) use ($since) {
        return $fish->created_at->timestamp > $since;
    })->values();

    // 構建完整的圖片路徑
    $expectedFishs->map(function ($fish) {
        $fish->image = env('SUPABASE_STORAGE_URL').'/object/public/'.env('SUPABASE_BUCKET') . '/images/' . $fish->image;
    });

    $expectedLastUpdateTime = $expectedFishs->isNotEmpty()
        ? $expectedFishs->max('updated_at')->timestamp
        : null;

    // 發送 GET 請求
    $response = $this->get('/prefix/api/fish?since='.$since);

    // 確保回應正確
    $response->assertStatus(200)
        ->assertJson([
            'message' => 'success',
            'data' => $expectedFishs->toArray(),
            'lastUpdateTime' => $expectedLastUpdateTime
        ])->assertJsonCount(2, 'data');
});

it('can get 6 fishes by time condition', function () {

    //條件時間
    $date = "2025/03/07";
    $since = strtotime($date);

    // 測試資料
    $fishs = Fish::factory()->count(9)->sequence(
        ['created_at' => \Carbon\Carbon::createFromTimestamp(strtotime('-1 day', $since)), 'updated_at' => \Carbon\Carbon::createFromTimestamp(strtotime('-1 day', $since))],
        ['created_at' => \Carbon\Carbon::createFromTimestamp(strtotime('-2 day', $since)), 'updated_at' => \Carbon\Carbon::createFromTimestamp(strtotime('+1 day', $since))],
        ['created_at' => \Carbon\Carbon::createFromTimestamp(strtotime('-3 days', $since)), 'updated_at' => \Carbon\Carbon::createFromTimestamp(strtotime('+2 days', $since))],
        ['created_at' => \Carbon\Carbon::createFromTimestamp(strtotime('+1 day', $since)), 'updated_at' => \Carbon\Carbon::createFromTimestamp(strtotime('-1 day', $since))],
        ['created_at' => \Carbon\Carbon::createFromTimestamp(strtotime('+2 day', $since)), 'updated_at' => \Carbon\Carbon::createFromTimestamp(strtotime('+1 day', $since))],
        ['created_at' => \Carbon\Carbon::createFromTimestamp(strtotime('+3 days', $since)), 'updated_at' => \Carbon\Carbon::createFromTimestamp(strtotime('+2 days', $since))],
        ['created_at' => \Carbon\Carbon::createFromTimestamp(strtotime('+4 day', $since)), 'updated_at' => \Carbon\Carbon::createFromTimestamp(strtotime('-1 day', $since))],
        ['created_at' => \Carbon\Carbon::createFromTimestamp(strtotime('+5 day', $since)), 'updated_at' => \Carbon\Carbon::createFromTimestamp(strtotime('+1 day', $since))],
        ['created_at' => \Carbon\Carbon::createFromTimestamp(strtotime('+6 days', $since)), 'updated_at' => \Carbon\Carbon::createFromTimestamp(strtotime('+2 days', $since))],
    )->create();

    $expectedFishs = $fishs->filter(function ($fish) use ($since) {
        return $fish->updated_at->timestamp > $since;
    })->values();

    // 構建完整的圖片路徑
    $expectedFishs->map(function ($fish) {
        $fish->image = env('SUPABASE_STORAGE_URL').'/object/public/'.env('SUPABASE_BUCKET') . '/images/' . $fish->image;
    });

    $expectedLastUpdateTime = $expectedFishs->isNotEmpty()
        ? $expectedFishs->max('updated_at')->timestamp
        : null;

    // 發送 GET 請求
    $response = $this->get('/prefix/api/fish?since='.$since);

    // 確保回應正確
    $response->assertStatus(200)
        ->assertJson([
            'message' => 'success',
            'data' => $expectedFishs->toArray(),
            'lastUpdateTime' => $expectedLastUpdateTime
        ])->assertJsonCount(6, 'data');
});

it('can get 0 fishes by time condition', function () {

    //條件時間
    $date = "2025/03/07";
    $since = strtotime($date);

    // 測試資料
    $fishs = Fish::factory()->count(9)->sequence(
        ['created_at' => \Carbon\Carbon::createFromTimestamp(strtotime('-1 day', $since)), 'updated_at' => \Carbon\Carbon::createFromTimestamp(strtotime('-1 day', $since))],
        ['created_at' => \Carbon\Carbon::createFromTimestamp(strtotime('-2 day', $since)), 'updated_at' => \Carbon\Carbon::createFromTimestamp(strtotime('-2 day', $since))],
        ['created_at' => \Carbon\Carbon::createFromTimestamp(strtotime('-3 days', $since)), 'updated_at' => \Carbon\Carbon::createFromTimestamp(strtotime('-3 days', $since))],
        ['created_at' => \Carbon\Carbon::createFromTimestamp(strtotime('-1 day', $since)), 'updated_at' => \Carbon\Carbon::createFromTimestamp(strtotime('-4 day', $since))],
    )->create();

    $expectedFishs = $fishs->filter(function ($fish) use ($since) {
        return $fish->updated_at->timestamp > $since;
    })->values();

    // 構建完整的圖片路徑
    $expectedFishs->map(function ($fish) {
        $fish->image = env('SUPABASE_STORAGE_URL').'/object/public/'.env('SUPABASE_BUCKET') . '/images/' . $fish->image;
    });

    $expectedLastUpdateTime = $expectedFishs->isNotEmpty()
        ? $expectedFishs->max('updated_at')->timestamp
        : null;

    // 發送 GET 請求
    $response = $this->get('/prefix/api/fish?since='.$since);

    // 確保回應正確
    $response->assertStatus(200)
        ->assertJson([
            'message' => 'No data available',
            'data' => null,
            'lastUpdateTime' => $expectedLastUpdateTime
        ])->assertJsonCount(0, 'data');
});

it('returns empty array when database is empty', function () {
    $since = strtotime("2025/03/07");
    
    $response = $this->get('/prefix/api/fish?since=' . $since);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'No data available',
            'data' => [],
            'lastUpdateTime' => null
        ])
        ->assertJsonCount(0, 'data');
});

it('handles invalid since parameter gracefully', function () {
    // 測試非數值
    $response = $this->get('/prefix/api/fish?since=invalid');
    $response->assertStatus(400)
        ->assertJson(['message' => 'Invalid since parameter'])
        ->assertJson(['data' => null]);
});

it('returns no data when since parameter is a future timestamp', function () {
    // 設定一個未來的時間戳
    $futureSince = strtotime('+10 days');

    // 建立一些舊資料
    Fish::factory()->count(3)->create([
        'created_at' => now()->subDays(5),
        'updated_at' => now()->subDays(5),
    ]);

    $response = $this->get('/prefix/api/fish?since=' . $futureSince);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'No data available',
            'data' => [],
        ])
        ->assertJsonCount(0, 'data');
});

it('returns error when since parameter is zero', function () {
    $response = $this->get('/prefix/api/fish?since=0');
    $response->assertStatus(400)
        ->assertJson(['message' => 'Invalid since parameter'])
        ->assertJson(['data' => null]);
});

it('returns error when since parameter is negative', function () {
    $response = $this->get('/prefix/api/fish?since=-12345');
    $response->assertStatus(400)
        ->assertJson(['message' => 'Invalid since parameter'])
        ->assertJson(['data' => null]);
});

it('does not return data when created_at is exactly equal to since', function () {
    $since = strtotime('2025-06-11 12:00:00');

    // 建立一筆 created_at 剛好等於 since 的資料
    $fishEqual = Fish::factory()->create([
        'created_at' => Carbon::createFromTimestamp($since),
        'updated_at' => Carbon::createFromTimestamp($since),
    ]);

    // 建立一筆 created_at 大於 since 的資料
    $fishGreater = Fish::factory()->create([
        'created_at' => Carbon::createFromTimestamp($since + 60),
        'updated_at' => Carbon::createFromTimestamp($since + 60),
    ]);

    $response = $this->get('/prefix/api/fish?since=' . $since);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'success',
        ])
        ->assertJsonMissing([
            'id' => $fishEqual->id,
        ])
        ->assertJsonFragment([
            'id' => $fishGreater->id,
        ]);
});
