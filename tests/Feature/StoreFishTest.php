<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('can create a fish', function () {

    // 測試資料
    $data = [
        'name' => 'ilek',
        'type' => 'oyod',
        'locate' => 'Iraraley',
        'image' => 'ilek.jpg',
    ];

    // 發送 POST 請求
    $response = $this->postJson('/api/fish', $data);

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
    $response = $this->postJson('/api/fish', $data);

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
    $response = $this->postJson('/api/fish', $data);

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
    $response = $this->postJson('/api/fish', $data);

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
    $response = $this->postJson('/api/fish', $data);

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
    $response = $this->postJson('/api/fish', $data);

    // 確保回應正確
    $response->assertStatus(201)
        ->assertJson([
            'message' => 'fish created successfully',
            'data' => $data,
        ]);

});

it('fish image can be uploaded, check response is 201 and message is image uploaded successfully', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('ilek.jpg');

    $response = $this->post('/api/fish/upload', [
        'image' => $file,
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'message' => 'image uploaded successfully',
            'data' => $file->hashName(),
        ]);
});

it('fish image can be uploaded, check image exist', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('ilek.jpg');

    $response = $this->post('/api/fish/upload', [
        'image' => $file,
    ]);

    Storage::disk('public')->assertExists('images/'.$file->hashName());
});

it('Fish image upload failed due to excessive file size', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('ilek.jpg')->size(10240);

    $response = $this->post('/api/fish/upload', [
        'image' => $file,
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'message' => 'image upload failed',
            'data' => [
                'image' => ['The image field must not be greater than 2048 kilobytes.'],
            ],
        ]);

});

it('Fish image upload failed due to an unsupported file type.', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->create('document.pdf', 1024);

    $response = $this->post('/api/fish/upload', [
        'image' => $file,
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'message' => 'image upload failed',
            'data' => [
                'image' => [
                    'The image field must be an image.',
                    'The image field must be a file of type: jpeg, png, jpg, gif, svg.',
                ],
            ],
        ]);

});
