<?php

use App\Contracts\StorageServiceInterface;
use App\Models\Fish;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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

it('image 為 null 的魚跳過不處理（直接寫入 DB 繞過 creating 事件）', function () {
    $fish = Fish::factory()->create();
    // Fish creating 事件會把 empty image 補成 'default.png'，需直接寫入 DB 繞過
    DB::table('fish')->where('id', $fish->id)->update(['image' => null]);

    $storage = $this->mock(StorageServiceInterface::class);
    $storage->shouldReceive('getWebpFolder')->once()->andReturn('webp');
    $storage->shouldReceive('fileExists')->never();

    $this->artisan('fish:check-webp')->assertSuccessful();
});

it('has_webp 未變更時不執行 save', function () {
    $fish = Fish::factory()->create(['image' => 'sample.jpg', 'has_webp' => true]);

    $storage = $this->mock(StorageServiceInterface::class);
    $storage->shouldReceive('getWebpFolder')->andReturn('webp');
    $storage->shouldReceive('fileExists')->with('webp/sample.webp')->andReturn(true);

    $this->artisan('fish:check-webp')->assertSuccessful();

    expect($fish->fresh()->has_webp)->toBeTruthy();
    expect($fish->fresh()->updated_at->eq($fish->updated_at))->toBeTrue();
});
