<?php

use Illuminate\Http\UploadedFile;

it('fish image can be uploaded, check response is 201 and message is image uploaded successfully', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('ilek.jpg', 640, 480);

    $response = $this->post('/prefix/api/upload', [
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

    $file = UploadedFile::fake()->image('ilek.jpg', 640, 480);

    $response = $this->post('/prefix/api/upload', [
        'image' => $file,
    ]);

    Storage::disk('public')->assertExists('images/'.$file->hashName());
});

it('Fish image upload failed due to excessive file size', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('ilek.jpg')->size(10240);

    $response = $this->post('/prefix/api/upload', [
        'image' => $file,
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'message' => '驗證失敗',
            'errors' => [
                'image' => ['圖片大小不可超過 4403 KB。'],
            ],
        ]);

});

it('Fish image upload failed due to an unsupported file type.', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->create('document.pdf', 1024);

    $response = $this->post('/prefix/api/upload', [
        'image' => $file,
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'message' => '驗證失敗',
            'errors' => [
                'image' => [
                    '只能上傳單一圖片檔案。',
                    '圖片格式僅限 jpeg, png, jpg, gif, svg。',
                ],
            ],
        ]);

});

it('fails when uploading an empty image file', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->create('empty.jpg', 0);

    $response = $this->post('/prefix/api/upload', [
        'image' => $file,
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'message' => '驗證失敗',
        ]);
});

it('fails when no image is provided', function () {
    Storage::fake('public');

    $response = $this->post('/prefix/api/upload', []);

    $response->assertStatus(400)
        ->assertJson([
            'message' => '驗證失敗',
            'errors' => [
                'image' => ['請選擇要上傳的圖片。'],
            ],
        ]);
});

it('fails when file extension is image but content is not', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->create('fake.jpg', 10, 'text/plain');

    $response = $this->post('/prefix/api/upload', [
        'image' => $file,
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'message' => '驗證失敗',
        ]);
});

it('fails when multiple images are provided', function () {
    Storage::fake('public');

    $file1 = UploadedFile::fake()->image('a.jpg');
    $file2 = UploadedFile::fake()->image('b.jpg');

    $response = $this->post('/prefix/api/upload', [
        'image' => [$file1, $file2],
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'message' => '驗證失敗',
        ]);
});

it('audio 檔案可以上傳，回應 201 並訊息為 audio uploaded successfully', function () {
    $audio = UploadedFile::fake()->create('test-audio.mp3', 100, 'audio/mpeg');

    $response = $this->postJson('/prefix/api/upload-audio', [
        'audio' => $audio,
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'message' => 'audio uploaded successfully',
        ]);

    // 檢查檔案是否真的被儲存
    $savedPath = 'audio/' . $audio->hashName();
    \Storage::disk('public')->assertExists($savedPath);
});
