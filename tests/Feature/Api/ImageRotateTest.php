<?php

use App\Contracts\StorageServiceInterface;
use App\Models\CaptureRecord;
use App\Models\Fish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->storageMock = $this->mock(StorageServiceInterface::class, function (MockInterface $mock) {
        $mock->shouldReceive('getImageFolder')->andReturn('images')->byDefault();
        $mock->shouldReceive('getWebpFolder')->andReturn('webp')->byDefault();
        $mock->shouldReceive('putContent')->andReturn(true)->byDefault();
        $mock->shouldReceive('getUrl')->andReturn('https://example.com/image.jpg')->byDefault();
    });
});

// ======================================================
// 魚類首圖旋轉
// ======================================================

describe('POST /prefix/api/fish/{id}/image/rotate', function () {

    it('editor 可旋轉魚類首圖', function () {
        $editor = User::factory()->create();
        $fish = Fish::factory()->create(['image' => 'fish.jpg', 'has_webp' => false]);
        $file = UploadedFile::fake()->image('rotated.jpg');

        $response = $this->actingAs($editor, 'sanctum')
            ->postJson("/prefix/api/fish/{$fish->id}/image/rotate", ['image' => $file]);

        $response->assertOk()->assertJson(['message' => 'success']);
    });

    it('viewer 無法旋轉魚類首圖（403）', function () {
        $viewer = User::factory()->lineViewer()->create();
        $fish = Fish::factory()->create(['image' => 'fish.jpg']);
        $file = UploadedFile::fake()->image('rotated.jpg');

        $response = $this->actingAs($viewer, 'sanctum')
            ->postJson("/prefix/api/fish/{$fish->id}/image/rotate", ['image' => $file]);

        $response->assertForbidden();
    });

    it('未登入無法旋轉魚類首圖（401）', function () {
        $fish = Fish::factory()->create(['image' => 'fish.jpg']);
        $file = UploadedFile::fake()->image('rotated.jpg');

        $response = $this->postJson("/prefix/api/fish/{$fish->id}/image/rotate", ['image' => $file]);

        $response->assertUnauthorized();
    });

    it('找不到魚類時回傳 404', function () {
        $editor = User::factory()->create();
        $file = UploadedFile::fake()->image('rotated.jpg');

        $response = $this->actingAs($editor, 'sanctum')
            ->postJson('/prefix/api/fish/9999/image/rotate', ['image' => $file]);

        $response->assertNotFound();
    });

    it('未傳入圖片時回傳 422', function () {
        $editor = User::factory()->create();
        $fish = Fish::factory()->create(['image' => 'fish.jpg']);

        $response = $this->actingAs($editor, 'sanctum')
            ->postJson("/prefix/api/fish/{$fish->id}/image/rotate", []);

        $response->assertUnprocessable();
    });

    it('has_webp = true 時同步覆蓋 WebP（putContent 呼叫兩次）', function () {
        $editor = User::factory()->create();
        $fish = Fish::factory()->create(['image' => 'fish.jpg', 'has_webp' => true]);
        $file = UploadedFile::fake()->image('rotated.jpg');

        $this->storageMock->shouldReceive('putContent')->twice()->andReturn(true);

        $this->actingAs($editor, 'sanctum')
            ->postJson("/prefix/api/fish/{$fish->id}/image/rotate", ['image' => $file])
            ->assertOk();
    });

    it('has_webp = false 時只覆蓋原圖（putContent 呼叫一次）', function () {
        $editor = User::factory()->create();
        $fish = Fish::factory()->create(['image' => 'fish.jpg', 'has_webp' => false]);
        $file = UploadedFile::fake()->image('rotated.jpg');

        $this->storageMock->shouldReceive('putContent')->once()->andReturn(true);

        $this->actingAs($editor, 'sanctum')
            ->postJson("/prefix/api/fish/{$fish->id}/image/rotate", ['image' => $file])
            ->assertOk();
    });
});

// ======================================================
// 捕獲紀錄圖片旋轉
// ======================================================

describe('POST /prefix/api/fish/{id}/capture-records/{recordId}/image/rotate', function () {

    it('editor 可旋轉捕獲紀錄圖片', function () {
        $editor = User::factory()->create();
        $fish = Fish::factory()->create();
        $record = CaptureRecord::factory()->create(['fish_id' => $fish->id, 'image_path' => 'record.jpg']);
        $file = UploadedFile::fake()->image('rotated.jpg');

        $response = $this->actingAs($editor, 'sanctum')
            ->postJson("/prefix/api/fish/{$fish->id}/capture-records/{$record->id}/image/rotate", ['image' => $file]);

        $response->assertOk()->assertJson(['message' => 'success']);
    });

    it('viewer 無法旋轉捕獲紀錄圖片（403）', function () {
        $viewer = User::factory()->lineViewer()->create();
        $fish = Fish::factory()->create();
        $record = CaptureRecord::factory()->create(['fish_id' => $fish->id, 'image_path' => 'record.jpg']);
        $file = UploadedFile::fake()->image('rotated.jpg');

        $response = $this->actingAs($viewer, 'sanctum')
            ->postJson("/prefix/api/fish/{$fish->id}/capture-records/{$record->id}/image/rotate", ['image' => $file]);

        $response->assertForbidden();
    });

    it('找不到捕獲紀錄時回傳 404', function () {
        $editor = User::factory()->create();
        $fish = Fish::factory()->create();
        $file = UploadedFile::fake()->image('rotated.jpg');

        $response = $this->actingAs($editor, 'sanctum')
            ->postJson("/prefix/api/fish/{$fish->id}/capture-records/9999/image/rotate", ['image' => $file]);

        $response->assertNotFound();
    });

    it('捕獲紀錄不屬於指定魚類時回傳 404', function () {
        $editor = User::factory()->create();
        $fish1 = Fish::factory()->create();
        $fish2 = Fish::factory()->create();
        $record = CaptureRecord::factory()->create(['fish_id' => $fish2->id, 'image_path' => 'record.jpg']);
        $file = UploadedFile::fake()->image('rotated.jpg');

        $response = $this->actingAs($editor, 'sanctum')
            ->postJson("/prefix/api/fish/{$fish1->id}/capture-records/{$record->id}/image/rotate", ['image' => $file]);

        $response->assertNotFound();
    });

    it('未傳入圖片時回傳 422', function () {
        $editor = User::factory()->create();
        $fish = Fish::factory()->create();
        $record = CaptureRecord::factory()->create(['fish_id' => $fish->id]);

        $response = $this->actingAs($editor, 'sanctum')
            ->postJson("/prefix/api/fish/{$fish->id}/capture-records/{$record->id}/image/rotate", []);

        $response->assertUnprocessable();
    });
});
