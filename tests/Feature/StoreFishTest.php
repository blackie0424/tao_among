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

it('can create a fish', function () {

    // 測試資料
    $data = [
        'name' => 'ilek',
        'type' => 'oyod',
        'locate' => 'Iraraley',
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
