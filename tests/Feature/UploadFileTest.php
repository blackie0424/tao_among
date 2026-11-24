<?php

use App\Models\Fish;
use App\Models\FishAudio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

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
                    '圖片格式僅限 jpeg, png, jpg, gif。',
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
                    '圖片格式僅限 jpeg, png, jpg, gif。',
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
    $savedPath = 'audio/'.$audio->hashName();
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
                'audio' => ['音訊格式僅限 mp3, wav, m4a'],
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
        'audio_filename' => 'test-audio.mp3',
    ]);

    $mockedUrl = 'https://s3.example.com/audios/uuid-audio.mp3?X-Amz-Signature=mock';

    $storage = $this->mock(\App\Contracts\StorageServiceInterface::class);
    $storage->shouldReceive('getAudioFolder')->andReturn('audios');
    $storage->shouldReceive('createSignedUploadUrl')->andReturn($mockedUrl);

    $response = $this->postJson("/prefix/api/fish/{$fishId}/storage/signed-upload-audio-url", [
        'filename' => 'test-audio.mp3',
    ]);

    $response->assertStatus(200)
        ->assertJson(
            fn ($json) => $json->where('url', $mockedUrl)
                ->where('path', fn ($path) => is_string($path) && ! empty($path))
                ->where('filename', fn ($filename) => is_string($filename) && ! empty($filename))
                ->etc()
        );
});

it('取得 storage image 檔案簽名上傳網址', function () {
    $mockedUrl = 'https://s3.example.com/images/uuid-image.jpg?X-Amz-Signature=mock';

    $storage = $this->mock(\App\Contracts\StorageServiceInterface::class);
    $storage->shouldReceive('getImageFolder')->andReturn('images');
    $storage->shouldReceive('createSignedUploadUrl')->andReturn($mockedUrl);

    $response = $this->postJson('/prefix/api/storage/signed-upload-url', [
        'filename' => 'test-image.jpg',
    ]);

    $response->assertStatus(200)
        ->assertJson(
            fn ($json) => $json->where('url', $mockedUrl)
                ->where('path', fn ($path) => is_string($path) && ! empty($path))
                ->where('filename', fn ($filename) => is_string($filename) && ! empty($filename))
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

it('取得音訊簽名上傳網址只簽名到登入者的暫存資料夾，不寫任何資料也不動主要發音', function () {
    $user = auth()->user();
    $fish = Fish::factory()->create(['audio_filename' => 'main.m4a']);

    $storage = $this->mock(\App\Contracts\StorageServiceInterface::class)->shouldIgnoreMissing();
    $storage->shouldReceive('createSignedUploadUrl')
        ->once()
        ->with(\Mockery::on(fn ($path) => str_starts_with($path, FishAudio::pendingPath($user->id, '')) && str_ends_with($path, '.m4a')), FishAudio::UPLOAD_TTL_SECONDS)
        ->andReturn('https://mocked-url-for-pending');

    $response = $this->postJson("/prefix/api/fish/{$fish->id}/storage/signed-upload-audio-url", [
        'filename' => 'test-audio.m4a',
    ]);

    $response->assertStatus(200)->assertJsonPath('url', 'https://mocked-url-for-pending');
    expect($response->json('filename'))->toMatch(FishAudio::filenamePattern());
    $this->assertDatabaseCount('fish_audios', 0);
    expect($fish->fresh()->audio_filename)->toBe('main.m4a');
});

it('取得音訊簽名上傳網址：m4a、aac、mp3、wav、webm 都可以，其他副檔名被拒絕', function (string $filename, int $status) {
    $fish = Fish::factory()->create();
    $this->mock(\App\Contracts\StorageServiceInterface::class)->shouldIgnoreMissing()
        ->shouldReceive('createSignedUploadUrl')->andReturn('https://mocked-url');

    $this->postJson("/prefix/api/fish/{$fish->id}/storage/signed-upload-audio-url", ['filename' => $filename])->assertStatus($status);
})->with([['a.m4a', 200], ['a.aac', 200], ['a.mp3', 200], ['a.wav', 200], ['a.webm', 200], ['a.exe', 422], ['a.ogg', 422]]);

it('取得音訊簽名上傳網址時魚不存在回 404', function () {
    $this->postJson('/prefix/api/fish/9999/storage/signed-upload-audio-url', ['filename' => 'a.m4a'])
        ->assertStatus(404);
});
