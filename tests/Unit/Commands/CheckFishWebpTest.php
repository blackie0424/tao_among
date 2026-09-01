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

    expect($fish->fresh()->has_webp)->toBeTruthy();
});

it('webp 不存在時將 has_webp 更新為 false', function () {
    $fish = Fish::factory()->create(['image' => 'sample.jpg', 'has_webp' => true]);

    $storage = $this->mock(StorageServiceInterface::class);
    $storage->shouldReceive('getWebpFolder')->andReturn('webp');
    $storage->shouldReceive('fileExists')->with('webp/sample.webp')->andReturn(false);

    $this->artisan('fish:check-webp')->assertSuccessful();

    expect($fish->fresh()->has_webp)->toBeFalsy();
});

it('has_webp 未變更時不執行 save', function () {
    $fish = Fish::factory()->create(['image' => 'sample.jpg', 'has_webp' => true]);

    $storage = $this->mock(StorageServiceInterface::class);
    $storage->shouldReceive('getWebpFolder')->andReturn('webp');
    $storage->shouldReceive('fileExists')->with('webp/sample.webp')->andReturn(true);

    // 在 factory create 之後才註冊監聽，避免捕捉到 create 本身的查詢
    $updateExecuted = false;
    \Illuminate\Support\Facades\DB::listen(function ($query) use (&$updateExecuted, $fish) {
        if (
            str_contains(strtolower($query->sql), 'update') &&
            str_contains(strtolower($query->sql), 'fish') &&
            in_array($fish->id, $query->bindings)
        ) {
            $updateExecuted = true;
        }
    });

    $this->artisan('fish:check-webp')->assertSuccessful();

    expect($fish->fresh()->has_webp)->toBeTruthy();
    expect($updateExecuted)->toBeFalse();
});
