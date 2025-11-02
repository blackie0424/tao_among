<?php

use App\Models\Fish;
use App\Services\SupabaseStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns 500 and no DB writes when moveObject fails during confirm', function () {
    // Bind a fake service where fileExists is true but moveObject fails (returns null)
    app()->bind(SupabaseStorageService::class, function () {
        return new class extends SupabaseStorageService {
            public function __construct() {}
            public function fileExists(string $filePath): bool { return true; }
            public function moveObject(string $sourcePath, string $destPath): ?string { return null; }
            public function getUrl(string $type, string $filename, ?bool $hasWebp = null): string
            { return 'https://cdn.example.com/audio/' . $filename; }
        };
    });

    $fish = Fish::create(['name' => 'MoveFailFish']);

    $pending = 'pending/audio/2025/11/01/'.$fish->id.'-willfail.webm';

    $res = $this->postJson('/prefix/api/upload/audio/confirm', [
        'fish_id' => $fish->id,
        'filePath' => $pending,
    ]);

    $res->assertStatus(500);

    // Ensure DB not written
    $finalName = pathinfo($pending, PATHINFO_FILENAME) . '.' . pathinfo($pending, PATHINFO_EXTENSION);

    $this->assertDatabaseMissing('fish_audios', [
        'fish_id' => $fish->id,
        'name' => $finalName,
    ]);

    $fish->refresh();
    expect($fish->audio_filename)->toBeNull();
});
