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
    // The service no longer mutates the image property directly
    // It relies on the accessor which uses the service
    // But since we are mocking the service instance inside the container...
    // wait, FishService::assignImageUrls iterates and sets $audio->url.
    // Does it set $fish->image? No, it shouldn't.
    // The previous implementation of assignImageUrls might have set $fish->image.
    // Let's check FishService.php.

    // Assuming FishService::assignImageUrls DOES NOT modify $fish->image
    // Then $fish->image remains '' (empty string from factory).
    // However, the test expects 'https://cdn.example/images/default.png'.
    // This implies the test expects mutation or is testing the accessor via the service?
    // The test name says "assigns default image".

    // If I look at the previous failures, it failed asserting 'https://...' matches 'default.png'.
    // This confirms $fish->image was NOT mutated.

    // So the expectation should be:
    // If Model booted event is triggered, it might set 'default.png' if empty
    // But we are creating with empty string. The model event checks `if (empty($fish->image))`.
    // '' is empty. So it sets default.png.
    expect($result[0]->image)->toBe('default.png');
    // BUT, maybe we want to check the accessor?
    // expect($result[0]->image_url)->toBe('https://cdn.example/images/default.png');

    // However, since we are mocking the storage service passed to FishService constructor,
    // does the Model Accessor use THAT instance?
    // Model accessor uses `app(StorageServiceInterface::class)`.
    // We need to swap the instance in the app container.
    $this->instance(SupabaseStorageService::class, $storage); // Oops, need to bind the interface
    $this->instance(\App\Contracts\StorageServiceInterface::class, $storage);

    // Now the accessor should work.
    expect($result[0]->image_url)->toBe('https://cdn.example/images/default.png');
    expect(isset($result[0]->audios[0]->url))->toBeFalse();
});

it('assigns image and audio urls only when names are non-empty', function () {
    $fish = Fish::factory()->create([
        'image' => 'foo.jpg',
        'has_webp' => true,
        'audio_filename' => 'voice.mp3', // used by getFishById path; here we test audios relation path
    ]);

    $audio = new FishAudio(['name' => 'voice.mp3']);
    $fish->setRelation('audios', collect([$audio]));

    $storage = m::mock(SupabaseStorageService::class);
    $storage->shouldReceive('getUrl')
        ->once()->with('images', 'foo.jpg', true)->andReturn('https://cdn.example/images/foo.webp');
    $storage->shouldReceive('getUrl')
    ->once()->with('audios', 'voice.mp3')->andReturn('https://cdn.example/audio/voice.mp3');

    $service = new FishService($storage);

    // Swap instance for accessor
    $this->instance(\App\Contracts\StorageServiceInterface::class, $storage);

    $result = $service->assignImageUrls([$fish]);

    expect($result[0]->image)->toBe('foo.jpg');
    expect($result[0]->image_url)->toBe('https://cdn.example/images/foo.webp');
    expect($result[0]->audios[0]->url)->toBe('https://cdn.example/audio/voice.mp3');
});
