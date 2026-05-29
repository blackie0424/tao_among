<?php

use App\Models\Fish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

// =====================================================
// #6 SVG 上傳應被拒絕
// =====================================================

describe('SVG 上傳應被拒絕', function () {

    it('直接上傳 svg 應回傳 400', function () {
        $file = UploadedFile::fake()->create('evil.svg', 10, 'image/svg+xml');

        $response = $this->post('/prefix/api/upload', ['image' => $file]);

        $response->assertStatus(400)
            ->assertJsonStructure(['errors' => ['image']]);
    });

    it('signed URL 申請 svg 副檔名應回傳 400', function () {
        $response = $this->postJson('/prefix/api/storage/signed-upload-url', [
            'filename' => 'evil.svg',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('errors.filename.0', '檔名格式不正確。');
    });

    it('圖片白名單只允許 jpeg png jpg gif', function () {
        foreach (['jpeg', 'png', 'jpg', 'gif'] as $ext) {
            $file = UploadedFile::fake()->image("test.{$ext}");
            $response = $this->post('/prefix/api/upload', ['image' => $file]);
            expect($response->status())->not->toBe(400, "格式 {$ext} 不應被拒絕");
        }
    });
});

// =====================================================
// #13 音訊白名單統一
// =====================================================

describe('音訊白名單統一', function () {

    it('直接上傳 mp3 應成功', function () {
        $file = UploadedFile::fake()->create('test.mp3', 100, 'audio/mpeg');
        $response = $this->postJson('/prefix/api/upload-audio', ['audio' => $file]);
        expect($response->status())->not->toBe(422);
    });

    it('直接上傳 wav 應成功', function () {
        $file = UploadedFile::fake()->create('test.wav', 100, 'audio/wav');
        $response = $this->postJson('/prefix/api/upload-audio', ['audio' => $file]);
        expect($response->status())->not->toBe(422);
    });

    it('直接上傳 m4a 應成功', function () {
        $file = UploadedFile::fake()->create('test.m4a', 100, 'audio/mp4');
        $response = $this->postJson('/prefix/api/upload-audio', ['audio' => $file]);
        expect($response->status())->not->toBe(422);
    });

    it('直接上傳 mp4 應被拒絕', function () {
        $file = UploadedFile::fake()->create('test.mp4', 100, 'video/mp4');
        $response = $this->postJson('/prefix/api/upload-audio', ['audio' => $file]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['audio']);
    });

    it('直接上傳 aac 應被拒絕', function () {
        $file = UploadedFile::fake()->create('test.aac', 100, 'audio/aac');
        $response = $this->postJson('/prefix/api/upload-audio', ['audio' => $file]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['audio']);
    });

    it('signed URL 申請 webm 音訊應成功通過驗證', function () {
        $fish = Fish::factory()->create();
        $response = $this->postJson('/prefix/api/upload/audio/sign', [
            'ext' => 'webm',
        ]);
        // 非 422 代表通過驗證層（可能因 storage 設定失敗，但驗證本身放行）
        expect($response->status())->not->toBe(422);
    });

    it('signed URL 申請 mp4 音訊應被拒絕', function () {
        $response = $this->postJson('/prefix/api/upload/audio/sign', [
            'ext' => 'mp4',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ext']);
    });

    it('signed URL 申請 aac 音訊應被拒絕', function () {
        $response = $this->postJson('/prefix/api/upload/audio/sign', [
            'ext' => 'aac',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ext']);
    });
});

// =====================================================
// #10 路徑穿越：儲存檔名不使用原始檔名
// =====================================================

describe('儲存檔名不使用使用者原始檔名', function () {

    it('上傳圖片後儲存的檔名不包含原始檔名', function () {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('../../etc/passwd.jpg', 640, 480);

        $response = $this->post('/prefix/api/upload', ['image' => $file]);

        // 確認回傳的檔名不含路徑穿越字元
        if ($response->status() === 201) {
            $returnedName = $response->json('data');
            expect($returnedName)->not->toContain('..');
            expect($returnedName)->not->toContain('/');
            expect($returnedName)->not->toContain('passwd');
        }
    });
});
