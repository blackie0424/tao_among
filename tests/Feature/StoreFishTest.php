<?php

it('can create a fish', function () {

    // 測試資料
    $data = [
        'name' => 'ilek',
        'type' => 'oyod',
        'locate' => 'Iraraley',
        'image' => 'ilek.jpg',
    ];

    // 發送 POST 請求
    $response = $this->postJson('/apifish', $data);

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
    $response = $this->postJson('/apifish', $data);

    // 確保回應正確
    $response->assertStatus(201)
        ->assertJson([
            'message' => 'fish can not created',
            'data' => $data,
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
    $response = $this->postJson('/apifish', $data);

    // 確保回應正確
    $response->assertStatus(201)
        ->assertJson([
            'message' => 'fish can not created',
            'data' => $data,
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
    $response = $this->postJson('/apifish', $data);

    // 確保回應正確
    $response->assertStatus(201)
        ->assertJson([
            'message' => 'fish can not created',
            'data' => $data,
        ]);

});

it('can  create a fish ,  fish type is empty string', function () {

    // 測試資料
    $data = [
        'name' => 'ilek',
        'type' => '',
        'locate' => 'Iraraley',
        'image' => '',
    ];

    // 發送 POST 請求
    $response = $this->postJson('/apifish', $data);

    // 確保回應正確
    $response->assertStatus(201)
        ->assertJson([
            'message' => 'fish can not created',
            'data' => $data,
        ]);

});

it('can  create a fish ,  fish type is null', function () {

    // 測試資料
    $data = [
        'name' => 'ilek',
        'type' => null,
        'locate' => 'Iraraley',
        'image' => '',
    ];

    // 發送 POST 請求
    $response = $this->postJson('/apifish', $data);

    // 確保回應正確
    $response->assertStatus(201)
        ->assertJson([
            'message' => 'fish can not created',
            'data' => $data,
        ]);

});
