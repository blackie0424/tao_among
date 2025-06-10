<?php

use App\Models\Fish;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can get fish list', function () {

    // 測試資料
    $fishs = Fish::factory()->count(3)->create();

    // 按照 id 降序排序，與 API 行為一致
    $fishs = $fishs->sortByDesc('id')->values();

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
            'data' => $fishs->toArray()
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
        'image' => 'ilek.jpg',
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
        'image' => 'ilek.png',
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
        'image' => 'ilek.png',
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

it('can update a fish record', function () {
    // 建立一筆魚類資料
    $fish = Fish::factory()->create([
        'name' => 'Original Name',
        'image' => 'original.png',
    ]);

    // 準備要更新的資料
    $updateData = [
        'name' => 'Updated Name',
        'image' => 'updated.png',
    ];

    // 發送 PUT 請求進行更新
    $response = $this->putJson('/prefix/api/fish/' . $fish->id, $updateData);

    // 驗證回應狀態與內容
    $response->assertStatus(200)
        ->assertJson([
            'message' => 'fish updated successfully',
            'data' => [
                'id' => $fish->id,
                'name' => 'Updated Name',
                'image' => 'updated.png',
            ],
        ]);

    // 驗證資料庫確實已更新
    $this->assertDatabaseHas('fish', [
        'id' => $fish->id,
        'name' => 'Updated Name',
        'image' => 'updated.png',
    ]);
});

it('returns 404 when updating a non-existent fish', function () {
    $updateData = [
        'name' => 'Not Exist',
        'image' => 'not_exist.png',
    ];

    // 假設 id 99999 不存在
    $response = $this->putJson('/prefix/api/fish/99999', $updateData);

    $response->assertStatus(404)
        ->assertJson([
            'message' => 'fish not found',
            'data' => null,
        ]);
});

it('returns 422 when updating a fish with invalid data', function () {
    // 建立一筆魚類資料
    $fish = Fish::factory()->create([
        'name' => 'Original Name',
        'image' => 'original.png',
    ]);

    // 傳送不合法資料（name 為空）
    $updateData = [
        'name' => '',
        'image' => 'updated.png',
    ];

    $response = $this->putJson('/prefix/api/fish/' . $fish->id, $updateData);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('returns 422 when updating a fish with too long name', function () {
    $fish = Fish::factory()->create([
        'name' => 'Original Name',
        'image' => 'original.png',
    ]);

    $updateData = [
        'name' => str_repeat('a', 300),
        'image' => 'updated.png',
    ];

    $response = $this->putJson('/prefix/api/fish/' . $fish->id, $updateData);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('returns 422 when updating a fish with wrong type', function () {
    $fish = Fish::factory()->create([
        'name' => 'Original Name',
        'image' => 'original.png',
    ]);

    $updateData = [
        'name' => ['array-not-string'],
        'image' => 12345,
    ];

    $response = $this->putJson('/prefix/api/fish/' . $fish->id, $updateData);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'image']);
});

it('ignores unknown fields when updating a fish', function () {
    $fish = Fish::factory()->create([
        'name' => 'Original Name',
        'image' => 'original.png',
    ]);

    $updateData = [
        'name' => 'Updated Name',
        'unknown_field' => 'should be ignored',
    ];

    $response = $this->putJson('/prefix/api/fish/' . $fish->id, $updateData);

    $response->assertStatus(200)
        ->assertJsonMissing(['unknown_field']);
});

it('returns 422 when updating a fish with empty body', function () {
    $fish = Fish::factory()->create([
        'name' => 'Original Name',
        'image' => 'original.png',
    ]);

    $response = $this->putJson('/prefix/api/fish/' . $fish->id, []);

    // 兩個欄位都沒傳，應該至少有一個錯誤
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['update']);
});

it('can delete a fish', function () {
    $fish = Fish::factory()->create();

    $response = $this->deleteJson('/prefix/api/fish/' . $fish->id);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Fish deleted successfully',
        ]);

    $this->assertDatabaseMissing('fish', [
        'id' => $fish->id,
    ]);
});

it('returns 404 when deleting a non-existent fish', function () {
    $invalidFishId = 99999;

    $response = $this->deleteJson('/prefix/api/fish/' . $invalidFishId);

    $response->assertStatus(404)
        ->assertJson([
            'message' => 'fish not found',
            'data' => null,
        ]);
});

