<?php
use App\Services\SupabaseStorageService;
use Illuminate\Support\Facades\Http;

it('can create signed upload url successfully', function () {
    $service = new SupabaseStorageService();
    $filePath = 'images/test.jpg';
    $expiresIn = 60;

    $storageUrl = env('SUPABASE_STORAGE_URL');
    $bucket = env('SUPABASE_BUCKET');
    $fakeUrl = "{$storageUrl}/object/upload/sign/{$bucket}/{$filePath}";

    Http::fake([
        $fakeUrl => Http::response([
            'url' => 'https://signed-upload-url.supabase.co/upload',
        ], 200),
    ]);

    $signedUrl = $service->createSignedUploadUrl($filePath, $expiresIn);

    expect($signedUrl)->toBe('https://signed-upload-url.supabase.co/upload');
});

it('returns null if create signed upload url fails', function () {
    $service = new SupabaseStorageService();
    $filePath = 'images/test.jpg';

    $storageUrl = env('SUPABASE_STORAGE_URL');
    $bucket = env('SUPABASE_BUCKET');
    $fakeUrl = "{$storageUrl}/object/upload/sign/{$bucket}/{$filePath}";

    Http::fake([
        $fakeUrl => Http::response([], 400),
    ]);

    $signedUrl = $service->createSignedUploadUrl($filePath);

    expect($signedUrl)->toBeNull();
});

it('can delete file successfully', function () {
    $service = new SupabaseStorageService();
    $filename = 'test.jpg';
    $storageUrl = env('SUPABASE_STORAGE_URL');
    $bucket = env('SUPABASE_BUCKET');
    $filePath = "images/{$filename}";
    $deleteUrl = "{$storageUrl}/object/{$bucket}/{$filePath}";

    Http::fake([
        $deleteUrl => Http::response([], 200),
    ]);

    $result = $service->delete($filename);

    expect($result)->toBeTrue();
});

it('returns false if delete file fails', function () {
    $service = new SupabaseStorageService();
    $filename = 'test.jpg';
    $storageUrl = env('SUPABASE_STORAGE_URL');
    $bucket = env('SUPABASE_BUCKET');
    $filePath = "images/{$filename}";
    $deleteUrl = "{$storageUrl}/object/{$bucket}/{$filePath}";

    Http::fake([
        $deleteUrl => Http::response([], 404),
    ]);

    $result = $service->delete($filename);

    expect($result)->toBeFalse();
});
