<?php

use App\Services\SupabaseStorageService;

uses(Tests\TestCase::class);

it('returns full url as-is without prefixing', function () {
    $service = app(SupabaseStorageService::class);
    $full = 'https://example.com/path/to/image.jpg';
    $out = $service->getUrl('images', $full, null);
    expect($out)->toBe($full);
});

it('builds webp url when hasWebp is true', function () {
    $service = app(SupabaseStorageService::class);
    $out = $service->getUrl('images', 'foo.jpg', true);
    $base = env('SUPABASE_STORAGE_URL');
    $bucket = env('SUPABASE_BUCKET');
    expect($out)->toBe("{$base}/object/public/{$bucket}/webp/foo.webp");
});

it('builds original image url when hasWebp is false or null', function () {
    $service = app(SupabaseStorageService::class);
    $base = env('SUPABASE_STORAGE_URL');
    $bucket = env('SUPABASE_BUCKET');

    $outFalse = $service->getUrl('images', 'bar.png', false);
    expect($outFalse)->toBe("{$base}/object/public/{$bucket}/images/bar.png");

    $outNull = $service->getUrl('images', 'baz.jpeg', null);
    expect($outNull)->toBe("{$base}/object/public/{$bucket}/images/baz.jpeg");
});

it('builds audio url only when name is present', function () {
    $service = app(SupabaseStorageService::class);
    $base = env('SUPABASE_STORAGE_URL');
    $bucket = env('SUPABASE_BUCKET');

    $out = $service->getUrl('audios', 'voice.mp3');
    expect($out)->toBe("{$base}/object/public/{$bucket}/audio/voice.mp3");
});
