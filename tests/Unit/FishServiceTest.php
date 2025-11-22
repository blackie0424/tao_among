<?php

use App\Models\Fish;
use App\Models\FishAudio;
use App\Services\FishService;
use App\Services\SupabaseStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery as m;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('assigns default image when image filename is empty and leaves audio url null', function () {
    // Arrange: create a fish with empty image and null audio filename
    $fish = Fish::factory()->create([
        'image' => '',
        'audio_filename' => null,
    ]);

    // Attach one audio relation with empty name to ensure it is ignored
    $audio = new FishAudio(['name' => '']);
    $fish->setRelation('audios', collect([$audio]));

    // Mock storage service to return URLs for default.png and specific names
    $storage = m::mock(SupabaseStorageService::class);
    $storage->shouldReceive('getUrl')
        ->once()
        ->with('images', 'default.png', false)
        ->andReturn('https://cdn.example/images/default.png');

    $service = new FishService($storage);

    // Act
    $result = $service->assignImageUrls([$fish]);

    // Assert
    expect($result)->toHaveCount(1);
    expect($result[0]->image)->toBe('https://cdn.example/images/default.png');
    // audio with empty name should not call getUrl, url remains unset
    expect(isset($result[0]->audios[0]->url))->toBeFalse();
});

it('assigns image and audio urls only when names are non-empty', function () {
    $fish = Fish::factory()->create([
        'image' => 'foo.jpg',
        'audio_filename' => 'bar.mp3', // used by getFishById path; here we test audios relation path
    ]);

    $audio = new FishAudio(['name' => 'voice.mp3']);
    $fish->setRelation('audios', collect([$audio]));

    $storage = m::mock(SupabaseStorageService::class);
    $storage->shouldReceive('getUrl')
        ->once()->with('images', 'foo.jpg')->andReturn('https://cdn.example/images/foo.webp');
    $storage->shouldReceive('getUrl')
        ->once()->with('audios', 'voice.mp3')->andReturn('https://cdn.example/audio/voice.mp3');

    $service = new FishService($storage);

    $result = $service->assignImageUrls([$fish]);

    expect($result[0]->image)->toBe('https://cdn.example/images/foo.webp');
    expect($result[0]->audios[0]->url)->toBe('https://cdn.example/audio/voice.mp3');
});
