<?php

use App\Models\Fish;
use App\Models\FishAudio;
use App\Services\FishService;
use App\Contracts\StorageServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery as m;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('assigns default image when image filename is empty and leaves audio url null', function () {
    // Arrange: create a fish with empty image and null audio filename
    $fish = Fish::factory()->create([
        'image' => '',
        'audio_filename' => null,
    ]);

    // name = null → accessor 回傳 null，確保 isset(url) 為 false
    $audio = new FishAudio(['name' => null]);
    $fish->setRelation('audios', collect([$audio]));

    // assignImageUrls 只處理 audios.url，不覆蓋 $fish->image（accessor 負責）
    $storage = m::mock(StorageServiceInterface::class);
    // name 為 null 時 assignImageUrls 跳過，不呼叫 getUrl
    $storage->shouldReceive('getUrl')->never();

    $service = new FishService($storage);

    // Act
    $result = $service->assignImageUrls([$fish]);

    // Assert
    expect($result)->toHaveCount(1);
    // Fish model booted hook 將空 image 正規化為 'default.png'
    expect($result[0]->image)->toBe('default.png');
    // name 為 null → accessor 回傳 null → isset 為 false
    expect(isset($result[0]->audios[0]->url))->toBeFalse();
});

it('assigns image and audio urls only when names are non-empty', function () {
    $fish = Fish::factory()->create([
        'image' => 'foo.jpg',
        'has_webp' => true,
        'audio_filename' => 'voice.mp3',
    ]);

    $audio = new FishAudio(['name' => 'voice.mp3']);
    $fish->setRelation('audios', collect([$audio]));

    $storage = m::mock(StorageServiceInterface::class);
    // assignImageUrls 只處理 audios.url（不處理 $fish->image）
    // FishAudio::url accessor 從 container 讀取，需同時 bind mock 至 container
    $storage->shouldReceive('getUrl')
        ->with('audios', 'voice.mp3')
        ->andReturn('https://cdn.example/audio/voice.mp3');
    // accessor 讀取時透過 container（帶第三參數 null），也需命中
    $storage->shouldReceive('getUrl')
        ->with('audios', 'voice.mp3', null)
        ->andReturn('https://cdn.example/audio/voice.mp3');
    app()->instance(StorageServiceInterface::class, $storage);

    $service = new FishService($storage);

    $result = $service->assignImageUrls([$fish]);

    // $fish->image 維持原始檔名，由 imageUrl accessor 負責轉換完整 URL
    expect($result[0]->image)->toBe('foo.jpg');
    // FishAudio::url accessor 從 container 讀取 → 使用上方 mock 回傳值
    expect($result[0]->audios[0]->url)->toBe('https://cdn.example/audio/voice.mp3');
});
