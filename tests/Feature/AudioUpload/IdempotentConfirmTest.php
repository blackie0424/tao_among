<?php

use App\Models\Fish;
use App\Services\SupabaseStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()->bind(SupabaseStorageService::class, function () {
        return new class extends SupabaseStorageService {
            public function __construct() {}
            public function createSignedUploadUrlForPendingAudio(int $fishId, string $ext = 'webm', int $expiresIn = 300): ?array
            {
                $filePath = "pending/audio/2025/11/01/{$fishId}-idem1234.{$ext}";
                return [
                    'uploadUrl' => 'https://fake.supabase.local/uploadUrl',
                    'filePath' => $filePath,
                    'expiresIn' => $expiresIn,
                ];
            }
            public function fileExists(string $filePath): bool { return true; }
            public function moveObject(string $sourcePath, string $destPath): ?string { return $destPath; }
            public function getUrl(string $type, string $filename, ?bool $hasWebp = null): string
            { return 'https://cdn.example.com/audio/' . $filename; }
        };
    });
});

it('confirm is idempotent: second call returns 200 with current state and no duplicates', function () {
    $fish = Fish::create(['name' => 'IdemFish']);

    // sign to produce a stable pending path
    $signRes = $this->postJson('/prefix/api/upload/audio/sign', [
        'fish_id' => $fish->id,
        'ext' => 'webm',
    ])->assertStatus(200);

    $filePath = $signRes->json('filePath');

    // first confirm
    $first = $this->postJson('/prefix/api/upload/audio/confirm', [
        'fish_id' => $fish->id,
        'filePath' => $filePath,
    ])->assertStatus(200);

    // second confirm (idempotent)
    $second = $this->postJson('/prefix/api/upload/audio/confirm', [
        'fish_id' => $fish->id,
        'filePath' => $filePath,
    ])->assertStatus(200);

    // DB duplicates check: exactly one row for fish + filename
    $finalName = pathinfo($filePath, PATHINFO_FILENAME) . '.' . pathinfo($filePath, PATHINFO_EXTENSION);

    $count = \DB::table('fish_audios')->where('fish_id', $fish->id)->where('name', $finalName)->count();
    expect($count)->toBe(1);
});
