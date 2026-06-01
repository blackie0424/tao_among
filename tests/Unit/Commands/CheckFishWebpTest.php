<?php

use App\Contracts\StorageServiceInterface;
use App\Models\Fish;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('webp 存在時將 has_webp 更新為 true', function () {
    $fish = Fish::factory()->create(['image' => 'sample.jpg', 'has_webp' => false]);

    $storage = $this->mock(StorageServiceInterface::class);
    $storage->shouldReceive('getWebpFolder')->andReturn('webp');
    $storage->shouldReceive('fileExists')->with('webp/sample.webp')->andReturn(true);

    $this->artisan('fish:check-webp')->assertSuccessful();

    expect($fish->fresh()->has_webp)->toBeTrue();
});

it('webp 不存在時將 has_webp 更新為 false', function () {
    $fish = Fish::factory()->create(['image' => 'sample.jpg', 'has_webp' => true]);

    $storage = $this->mock(StorageServiceInterface::class);
    $storage->shouldReceive('getWebpFolder')->andReturn('webp');
    $storage->shouldReceive('fileExists')->with('webp/sample.webp')->andReturn(false);

    $this->artisan('fish:check-webp')->assertSuccessful();

    expect($fish->fresh()->has_webp)->toBeFalse();
});

it('image 為 null 的魚跳過不處理', function () {
    $fish = Fish::factory()->create(['image' => null, 'has_webp' => false]);

    $storage = $this->mock(StorageServiceInterface::class);
    // getWebpFolder 仍會被呼叫一次（command 在 chunk 前取資料夾名稱）
    $storage->shouldReceive('getWebpFolder')->once()->andReturn('webp');
    $storage->shouldReceive('fileExists')->never();

    $this->artisan('fish:check-webp')->assertSuccessful();

    expect($fish->fresh()->has_webp)->toBeFalse();
});

it('has_webp 未變更時不執行 save', function () {
    $fish = Fish::factory()->create(['image' => 'sample.jpg', 'has_webp' => true]);

    $storage = $this->mock(StorageServiceInterface::class);
    $storage->shouldReceive('getWebpFolder')->andReturn('webp');
    $storage->shouldReceive('fileExists')->with('webp/sample.webp')->andReturn(true);

    $this->artisan('fish:check-webp')->assertSuccessful();

    expect($fish->fresh()->has_webp)->toBeTrue();
    expect($fish->fresh()->updated_at->eq($fish->updated_at))->toBeTrue();
});
