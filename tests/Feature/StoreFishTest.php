<?php

use App\Models\Fish;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can get fish list', function () {

    // 測試資料
    $fishs = Fish::factory()->count(3)->create();

    // 構建完整的圖片路徑
    $fishsWithImageUrl = $fishs->map(function ($fish) {
        // 假設你的 ASSET_URL 是存儲在 config('app.asset_url') 中
        $fish->image = env('ASSET_URL').'/images/'.$fish->image;
    });

    // 發送 GET 請求
    $response = $this->get('/prefix/api/fish');

    // 確保回應正確
    $response->assertStatus(200)
        ->assertJson([
            'message' => 'success',
            'data' => $fishs->toArray(),
            'lastUpdateTime' => time()
        ]);
});

it('can get no data', function () {

    // 測試資料
    $fishs = Fish::factory()->count(0)->create();

    // 發送 GET 請求
    $response = $this->get('/prefix/api/fish');

    // 確保回應正確
    $response->assertStatus(200)
        ->assertJson([
            'message' => 'No data available',
            'data' => $fishs->toArray(),
        ]);
});

it('can get a fish data by fish id', function () {

    // 測試資料
    $fish = Fish::factory()->create();

    // 構建完整的圖片路徑
    $fish->image = env('ASSET_URL').'/images/'.$fish->image;

    // 發送 GET 請求
    $response = $this->get('/prefix/api/fish/'.$fish->id);

    // 確保回應正確
    $response->assertStatus(200)
        ->assertJson([
            'message' => 'success',
            'data' => $fish->toArray(),
        ]);
});

it('get http code 404 beacuse fish id is not number', function () {

    // 測試資料
    $fish = Fish::factory()->create();

    // 構建完整的圖片路徑
    $fish->image = env('ASSET_URL').'/images/'.$fish->image;

    // 發送 GET 請求
    $response = $this->get('/prefix/api/fish/fakeString');

    // 確保回應正確
    $response->assertStatus(404);
});

it('can not find fish data beacuse fish id is not exist', function () {

    // 測試資料
    $fish = Fish::factory()->create();

    // 構建完整的圖片路徑
    $fish->image = env('ASSET_URL').'/images/'.$fish->image;

    // 發送 GET 請求
    $response = $this->get('/prefix/api/fish/'.$fish->id + 1);

    // 確保回應正確
    $response->assertStatus(404)
        ->assertJson([
            'message' => 'data not found',
        ]);
});

it('can create a fish', function () {

    // 測試資料
    $data = [
        'name' => 'ilek',
        'type' => 'oyod',
        'locate' => 'Iraraley',
        'image' => 'ilek.jpg',
        'process' => 'isisan'
    ];

    // 發送 POST 請求
    $response = $this->postJson('/prefix/api/fish', $data);

    // 確保回應正確
    $response->assertStatus(201)
        ->assertJson([
            'message' => 'fish created successfully',
            'data' => $data,
        ]);

    // 確保資料正確儲存到 DB
    $this->assertDatabaseHas('fish', $data);
});

it('can not  create a fish ,  fish name is empty', function () {

    // 測試資料
    $data = [
        'name' => '',
        'type' => 'oyod',
        'locate' => 'Iraraley',
        'image' => 'ilek.jpg',
        'process' => 'isisan'
    ];

    // 發送 POST 請求
    $response = $this->postJson('/prefix/api/fish', $data);

    // 確保回應正確
    $response->assertStatus(422)
        ->assertJson([
            'message' => 'The name field is required.',
            'errors' => [
                'name' => ['The name field is required.'],
            ],
        ]);

});

it('can not  create a fish ,  fish locate is empty', function () {

    // 測試資料
    $data = [
        'name' => 'ilek',
        'type' => 'oyod',
        'locate' => '',
        'image' => 'ilek.jpg',
        'process' => 'isisan'
    ];

    // 發送 POST 請求
    $response = $this->postJson('/prefix/api/fish', $data);

    // 確保回應正確
    $response->assertStatus(422)
        ->assertJson([
            'message' => 'The locate field is required.',
            'errors' => [
                'locate' => ['The locate field is required.'],
            ],
        ]);

});

it('can not  create a fish ,  fish image is empty', function () {

    // 測試資料
    $data = [
        'name' => 'ilek',
        'type' => 'oyod',
        'locate' => 'Iraraley',
        'image' => '',
        'process' => 'isisan'
    ];

    // 發送 POST 請求
    $response = $this->postJson('/prefix/api/fish', $data);

    // 確保回應正確
    $response->assertStatus(422)
        ->assertJson([
            'message' => 'The image field is required.',
            'errors' => [
                'image' => ['The image field is required.'],
            ],
        ]);

});

it('can  create a fish ,  fish type is empty string', function () {

    // 測試資料
    $data = [
        'name' => 'ilek',
        'type' => '',
        'locate' => 'Iraraley',
        'image' => 'ilek.png',
        'process' => 'isisan'
    ];

    // 發送 POST 請求
    $response = $this->postJson('/prefix/api/fish', $data);

    // 確保回應正確
    $response->assertStatus(201)
        ->assertJson([
            'message' => 'fish created successfully',
            'data' => $data,
        ]);

});

it('can  create a fish ,  fish type is null', function () {

    // 測試資料
    $data = [
        'name' => 'ilek',
        'type' => null,
        'locate' => 'Iraraley',
        'image' => 'ilek.png',
        'process' => 'isisan'
    ];

    // 發送 POST 請求
    $response = $this->postJson('/prefix/api/fish', $data);

    // 確保回應正確
    $response->assertStatus(201)
        ->assertJson([
            'message' => 'fish created successfully',
            'data' => $data,
        ]);

});

it('can not  create a fish ,  missing  a process data', function () {

    // 測試資料
    $data = [
        'name' => 'ilek',
        'type' => 'oyod',
        'locate' => 'Iraraley',
        'image' => 'ilek.png',
    ];

    // 發送 POST 請求
    $response = $this->postJson('/prefix/api/fish', $data);

    // 確保回應正確
    $response->assertStatus(422)
    ->assertJson([
        'message' => 'The process field is required.',
        'errors' => [
            'process' => ['The process field is required.'],
        ],
    ]);

});

it('can not  create a fish ,  missing  are process and locate data', function () {

    // 測試資料
    $data = [
        'name' => 'ilek',
        'type' => 'oyod',
        'image' => 'ilek.png',
    ];

    // 發送 POST 請求
    $response = $this->postJson('/prefix/api/fish', $data);

    // 確保回應正確
    $response->assertStatus(422)
    ->assertJson([
        'message' => 'The locate field is required. (and 1 more error)',
    ]);

});

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
        $fish->image = env('ASSET_URL') . '/images/' . $fish->image;
    });

    // 發送 GET 請求
    $response = $this->get('/prefix/api/fish?since='.$since);

    // 確保回應正確
    $response->assertStatus(200)
        ->assertJson([
            'message' => 'success',
            'data' => $expectedFishs->toArray(),
            'lastUpdateTime' => time()
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
        return $fish->created_at->timestamp > $since;
    })->values();

    // 構建完整的圖片路徑
    $expectedFishs->map(function ($fish) {
        $fish->image = env('ASSET_URL') . '/images/' . $fish->image;
    });

    // 發送 GET 請求
    $response = $this->get('/prefix/api/fish?since='.$since);

    // 確保回應正確
    $response->assertStatus(200)
        ->assertJson([
            'message' => 'success',
            'data' => $expectedFishs->toArray(),
            'lastUpdateTime' => time()
        ])->assertJsonCount(6, 'data');
});

it('can get 0 fishes by time condition', function () {

    //條件時間
    $date = "2025/03/07";
    $since = strtotime($date);

    // 測試資料
    $fishs = Fish::factory()->count(9)->sequence(
        ['created_at' => \Carbon\Carbon::createFromTimestamp(strtotime('-1 day', $since)), 'updated_at' => \Carbon\Carbon::createFromTimestamp(strtotime('-1 day', $since))],
        ['created_at' => \Carbon\Carbon::createFromTimestamp(strtotime('-2 day', $since)), 'updated_at' => \Carbon\Carbon::createFromTimestamp(strtotime('+1 day', $since))],
        ['created_at' => \Carbon\Carbon::createFromTimestamp(strtotime('-3 days', $since)), 'updated_at' => \Carbon\Carbon::createFromTimestamp(strtotime('+2 days', $since))],
        ['created_at' => \Carbon\Carbon::createFromTimestamp(strtotime('-1 day', $since)), 'updated_at' => \Carbon\Carbon::createFromTimestamp(strtotime('-1 day', $since))],
    )->create();

    $expectedFishs = $fishs->filter(function ($fish) use ($since) {
        return $fish->created_at->timestamp > $since;
    })->values();

    // 構建完整的圖片路徑
    $expectedFishs->map(function ($fish) {
        $fish->image = env('ASSET_URL') . '/images/' . $fish->image;
    });

    // 發送 GET 請求
    $response = $this->get('/prefix/api/fish?since='.$since);

    // 確保回應正確
    $response->assertStatus(200)
        ->assertJson([
            'message' => 'No data available',
            'data' => null,
            'lastUpdateTime' => time()
        ])->assertJsonCount(0, 'data');
});

it('returns empty array when database is empty', function () {
    $since = strtotime("2025/03/07");
    $response = $this->get('/prefix/api/fish?since=' . $since);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'No data available',
            'data' => [],
            'lastUpdateTime' => time()
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
