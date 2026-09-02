<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

describe('getSignedUploadUrl', function () {
    it('允許 images 資料夾', function () {
        $this->actingAs($this->admin)
            ->postJson('/prefix/api/storage/signed-upload-url', [
                'filename' => 'test.jpg',
                'folder' => 'images',
            ])
            ->assertOk()
            ->assertJsonStructure(['url', 'path', 'filename']);
    });

    it('允許 intro-slides 資料夾', function () {
        $this->actingAs($this->admin)
            ->postJson('/prefix/api/storage/signed-upload-url', [
                'filename' => 'slide.png',
                'folder' => 'intro-slides',
            ])
            ->assertOk()
            ->assertJsonStructure(['url', 'path', 'filename']);
    });

    it('拒絕不在白名單的資料夾', function () {
        $this->actingAs($this->admin)
            ->postJson('/prefix/api/storage/signed-upload-url', [
                'filename' => 'test.jpg',
                'folder' => 'malicious',
            ])
            ->assertStatus(400)
            ->assertJson([
                'message' => '驗證失敗',
            ]);
    });

    it('folder 參數為空時預設使用 images', function () {
        $this->actingAs($this->admin)
            ->postJson('/prefix/api/storage/signed-upload-url', [
                'filename' => 'test.jpg',
            ])
            ->assertOk()
            ->assertJson(fn ($json) =>
                $json->where('path', fn ($path) => str_starts_with($path, 'images/'))
                    ->etc()
            );
    });
});
