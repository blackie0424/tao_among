<?php

use App\Models\Fish;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\SupabaseStorageService;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('returns default image url when image is empty', function () {
    $fish = Fish::factory()->create(['image' => null, 'has_webp' => true]);
    $url = $fish->image_url; // accessor
    $base = env('SUPABASE_STORAGE_URL');
    $bucket = env('SUPABASE_BUCKET');
    expect($url)->toBe("{$base}/object/public/{$bucket}/webp/default.webp");
});

it('returns webp url when has_webp is true', function () {
    $fish = Fish::factory()->create(['image' => 'sample.jpg', 'has_webp' => true]);
    $url = $fish->image_url;
    $base = env('SUPABASE_STORAGE_URL');
    $bucket = env('SUPABASE_BUCKET');
    expect($url)->toBe("{$base}/object/public/{$bucket}/webp/sample.webp");
});

it('returns original image url when has_webp is false', function () {
    $fishFalse = Fish::factory()->create(['image' => 'a.png', 'has_webp' => false]);
    expect($fishFalse->image_url)->toBe(app(SupabaseStorageService::class)->getUrl('images', $fishFalse->image, $fishFalse->has_webp));
});

it('passes through full url stored in image field', function () {
    $full = 'https://cdn.example.com/prebuilt/path/x.jpg';
    $fish = Fish::factory()->create(['image' => $full, 'has_webp' => true]);
    expect($fish->image_url)->toBe($full);
});
