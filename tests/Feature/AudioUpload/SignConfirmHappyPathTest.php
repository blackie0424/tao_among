<?php

use App\Models\Fish;
use App\Models\FishAudio;
use App\Services\SupabaseStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Bind a fake Supabase service to avoid real HTTP/env
    app()->bind(SupabaseStorageService::class, function () {
        return new class extends SupabaseStorageService {
            public function __construct() {}
            public function createSignedUploadUrlForPendingAudio(int $fishId, string $ext = 'webm', int $expiresIn = 300): ?array
            {
                $filePath = "pending/audio/2025/11/01/{$fishId}-abcd1234.{$ext}";
                return [
                    'uploadUrl' => 'https://fake.supabase.local/uploadUrl',
                    'filePath' => $filePath,
                    'expiresIn' => $expiresIn,
                ];
            }
            public function fileExists(string $filePath): bool
            {
                return str_starts_with($filePath, 'pending/audio/');
            }
            public function moveObject(string $sourcePath, string $destPath): ?string
            {
                return $destPath;
            }
            public function getUrl(string $type, string $filename, ?bool $hasWebp = null): string
            {
                if ($type === 'audio' || $type === 'audios') {
                    return 'https://cdn.example.com/audio/' . $filename;
                }
                return parent::getUrl($type, $filename, $hasWebp);
            }
        };
    });
});

it('sign -> confirm happy path persists and returns 200 with url', function () {
    // Arrange: create a fish record
    $fish = Fish::create(['name' => 'TestFish']);

    // Act: sign pending URL
    $signRes = $this->postJson('/prefix/api/upload/audio/sign', [
        'fish_id' => $fish->id,
        'ext' => 'webm',
    ]);

    $signRes->assertStatus(200)
        ->assertJsonStructure(['uploadUrl', 'filePath', 'expiresIn']);

    $filePath = $signRes->json('filePath');

    // Act: confirm
    $confirmRes = $this->postJson('/prefix/api/upload/audio/confirm', [
        'fish_id' => $fish->id,
        'filePath' => $filePath,
    ]);

    // Assert: 200 and state confirmed
    $confirmRes->assertStatus(200)
        ->assertJsonPath('state', 'confirmed')
        ->assertJsonStructure(['url', 'filename', 'state']);

    $finalName = pathinfo($filePath, PATHINFO_FILENAME) . '.' . pathinfo($filePath, PATHINFO_EXTENSION);

    // DB assertions
    $this->assertDatabaseHas('fish_audios', [
        'fish_id' => $fish->id,
        'name' => $finalName,
    ]);

    $fish->refresh();
    expect($fish->audio_filename)->toBe($finalName);
});
