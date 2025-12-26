<?php

use Illuminate\Http\UploadedFile;

use Illuminate\Foundation\Testing\RefreshDatabase; // 加入這行
use App\Models\Fish;
use App\Models\FishAudio;
use Mockery;
use Mockery\MockInterface;
use Exception;

uses(RefreshDatabase::class); // Pest 測試自動 migrate，確保資料表存在



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

it('image 上傳失敗，檔案格式為 heic', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->create('photo.heic', 100, 'image/heic');

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

it('audio 上傳失敗，未提供檔案', function () {
    $response = $this->postJson('/prefix/api/upload-audio', []);
    $response->assertStatus(422)
        ->assertJson([
            'message' => '資料驗證失敗',
            'errors' => [
                'audio' => ['請選擇要上傳的音訊檔案。'],
            ],
        ]);
});

it('audio 上傳失敗，檔案格式錯誤', function () {
    $file = UploadedFile::fake()->create('not-audio.txt', 100, 'text/plain');
    $response = $this->postJson('/prefix/api/upload-audio', [
        'audio' => $file,
    ]);
    $response->assertStatus(422)
        ->assertJson([
            'message' => '資料驗證失敗',
            'errors' => [
                'audio' => ['音訊格式僅限 mp3, wav'],
            ],
        ]);
});

it('audio 上傳失敗，檔案過大', function () {
    $file = UploadedFile::fake()->create('big-audio.mp3', 20480, 'audio/mpeg'); // 20MB
    $response = $this->postJson('/prefix/api/upload-audio', [
        'audio' => $file,
    ]);
    $response->assertStatus(422)
        ->assertJson([
            'message' => '資料驗證失敗',
            'errors' => [
                'audio' => ['音訊大小不可超過 10MB。'],
            ],
        ]);
});

it('audio 上傳失敗，傳送多個檔案', function () {
    $file1 = UploadedFile::fake()->create('a.mp3', 10, 'audio/mpeg');
    $file2 = UploadedFile::fake()->create('b.mp3', 10, 'audio/mpeg');
    $response = $this->postJson('/prefix/api/upload-audio', [
        'audio' => [$file1, $file2],
    ]);
    $response->assertStatus(422)
        ->assertJson([
            'message' => '資料驗證失敗',
        ]);
});

it('audio 上傳失敗，檔案為 0 位元組', function () {
    $file = UploadedFile::fake()->create('empty.mp3', 0, 'audio/mpeg');
    $response = $this->postJson('/prefix/api/upload-audio', [
        'audio' => $file,
    ]);
    $response->assertStatus(422)
        ->assertJson([
            'message' => '資料驗證失敗',
        ]);
});

it('audio 上傳失敗，副檔名為 mp3 但內容不是 audio', function () {
    $file = UploadedFile::fake()->create('fake-audio.mp3', 10, 'text/plain');
    $response = $this->postJson('/prefix/api/upload-audio', [
        'audio' => $file,
    ]);
    $response->assertStatus(422)
        ->assertJson([
            'message' => '資料驗證失敗',
        ]);
});

it('取得 storage audio 檔案簽名上傳網址', function () {
    $fishId = 999;

    Fish::factory()->create([
        'id' => $fishId,
    ]);

    Http::fake([
        // 修正 URL 模式，使用萬用字元
            '*/object/upload/sign/*' => Http::response([
                'url' => 'https://supabase.storage.mock/audios/test-audio.mp3?token=mocked_token',
                'path' => 'audios/test-audio.mp3',
                'filename' => 'test-audio.mp3',
            ], 200),
        ]);

    $response = $this->postJson("/prefix/api/fish/{$fishId}/storage/signed-upload-audio-url", [
        'filename' => 'test-audio.mp3'
    ]);

    $response->assertStatus(200)
    ->assertJson(
        fn ($json) =>
        $json->where('url', 'https://supabase.storage.mock/audios/test-audio.mp3?token=mocked_token')
             // 🎯 使用 where() 方法來對動態值執行閉包檢查
             ->where('path', fn ($path) => is_string($path) && !empty($path))
             ->where('filename', fn ($filename) => is_string($filename) && !empty($filename))
             // 確保沒有其他不相關的鍵影響斷言
             ->etc()
    );
});

it('取得 storage image 檔案簽名上傳網址', function () {
    Http::fake([
        // 修正 URL 模式，使用萬用字元
            '*/object/upload/sign/*' => Http::response([
                'url' => 'https://supabase.storage.mock/images/test-image.jpg?token=mocked_token',
                'path' => 'images/test-image.jpg',
                'filename' => 'test-image.jpg',
            ], 200),
        ]);
    
    $response = $this->postJson('/prefix/api/storage/signed-upload-url', [
        'filename' => 'test-image.jpg',
    ]);

    $response->assertStatus(200)
    ->assertJson(
        fn ($json) =>
        $json->where('url', 'https://supabase.storage.mock/images/test-image.jpg?token=mocked_token')
             // 🎯 使用 where() 方法來對動態值執行閉包檢查
             ->where('path', fn ($path) => is_string($path) && !empty($path))
             ->where('filename', fn ($filename) => is_string($filename) && !empty($filename))
             // 確保沒有其他不相關的鍵影響斷言
             ->etc()
    );
});

it('取得 storage image 檔案簽名上傳網址失敗，副檔名錯誤', function () {
    $response = $this->postJson('/prefix/api/storage/signed-upload-url', [
        'filename' => 'test-image.exe',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'message' => '驗證失敗',
            'errors' => [
                'filename' => ['檔名格式不正確。'],
            ],
        ]);
});

it('確認聲音或圖像的檔案上傳後，資料是否能寫入資料庫', function () {
    $fishId = 999;

    $fish = Fish::factory()->create([
        'id' => $fishId,
        'image' => 'test-image.jpg',
        'audio_filename' => 'test-audio.mp3',
    ]);

    // 1. 使用 spy() 綁定服務，並將實例儲存在 $serviceSpy 中
    $serviceSpy = $this->spy(\App\Contracts\StorageServiceInterface::class);

    // 2. 告訴 $serviceSpy，當它收到 'createSignedUploadUrl' 呼叫時，要回傳什麼？
    $serviceSpy->shouldReceive('createSignedUploadUrl')
        ->andReturn('https://mocked-url-for-db-test');

    // 3. 執行請求 (Action)
    $response = $this->postJson("/prefix/api/fish/{$fishId}/storage/signed-upload-audio-url", [
        'filename' => 'test-audio.mp3'
    ]);

    // 4. 斷言狀態碼
    $response->assertStatus(200);
});

it('當聲音檔案上傳後，要將聲音檔案的資料寫入資料表發生錯誤時，應在 DB 交易失敗時，確保資料庫回滾且不新增任何紀錄', function () {
    
    $fishId = 999;

    $fish = Fish::factory()->create([
        'id' => $fishId,
        'image' => 'test-image.jpg',
        'audio_filename' => 'test-audio.mp3',
    ]);

    // 1. 使用 spy() 綁定服務，並將實例儲存在 $serviceSpy 中
    $serviceSpy = $this->spy(\App\Contracts\StorageServiceInterface::class);

    // 2. 服務模擬：設定 spy (此處不影響測試，但保留以保持完整性)
    $serviceSpy->shouldReceive('createSignedUploadUrl')
        ->andReturn('https://mocked-url-for-rollback');


    // // 3. 模擬失敗：強制 FishAudio::create 拋出例外
    $this->partialMock(FishAudio::class, function (MockInterface $mock) {
        // 🎯 這裡使用 $mock 變數來設定 shouldReceive
        $mock->shouldReceive('create')
             ->once()
             ->andThrow(new \Exception('Simulated rollback failure'));
    });
    // // 4. 執行請求與斷言狀態碼 (Action & Status Assertion)
    $response = $this->postJson("/prefix/api/fish/{$fishId}/storage/signed-upload-audio-url", [
        'filename' => 'test-audio.mp3'
    ]);

    $response->assertStatus(500); // 期望收到 500 錯誤
    
    // 預期 JSON 訊息片段 (來自 Controller 的 catch 區塊)
    $response->assertJsonFragment([
        'message' => '儲存音訊 metadata 失敗',
    ]);
    // 5. 確認資料庫中沒有新增任何 FishAudio 紀錄
    $this->assertDatabaseCount('fish_audios', 0);

});
