<?php

use App\Contracts\StorageServiceInterface;
use App\Models\Fish;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('returns default image url when image is empty', function () {
    $storage = $this->mock(StorageServiceInterface::class);
    $storage->shouldReceive('getUrl')
        ->with('images', 'default.png', false)
        ->andReturn('https://s3.example.com/images/default.png');

    $fish = Fish::factory()->create(['image' => null, 'has_webp' => false]);
    expect($fish->image_url)->toBe('https://s3.example.com/images/default.png');
});

it('returns webp url when has_webp is true', function () {
    $storage = $this->mock(StorageServiceInterface::class);
    $storage->shouldReceive('getUrl')
        ->with('images', 'sample.jpg', true)
        ->andReturn('https://s3.example.com/webp/sample.webp');

    $fish = Fish::factory()->create(['image' => 'sample.jpg', 'has_webp' => true]);
    expect($fish->image_url)->toBe('https://s3.example.com/webp/sample.webp');
});

it('returns original image url when has_webp is false', function () {
    $storage = $this->mock(StorageServiceInterface::class);
    $storage->shouldReceive('getUrl')
        ->with('images', 'a.png', false)
        ->andReturn('https://s3.example.com/images/a.png');

    $fish = Fish::factory()->create(['image' => 'a.png', 'has_webp' => false]);
    expect($fish->image_url)->toBe('https://s3.example.com/images/a.png');
});

it('passes through full url stored in image field', function () {
    $full = 'https://cdn.example.com/prebuilt/path/x.jpg';

    $storage = $this->mock(StorageServiceInterface::class);
    $storage->shouldReceive('getUrl')
        ->with('images', $full, true)
        ->andReturn($full);

    $fish = Fish::factory()->create(['image' => $full, 'has_webp' => true]);
    expect($fish->image_url)->toBe($full);
});
