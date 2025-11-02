<?php

use App\Models\Fish;
use App\Services\SupabaseStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('confirm returns 409 when pending object is missing and does not write DB', function () {
    // Bind a fake service that reports missing pending objects
    app()->bind(SupabaseStorageService::class, function () {
        return new class extends SupabaseStorageService {
            public function __construct() {}
            public function fileExists(string $filePath): bool { return false; }
            public function getUrl(string $type, string $filename, ?bool $hasWebp = null): string
            { return 'https://cdn.example.com/audio/' . $filename; }
        };
    });

    $fish = Fish::create(['name' => 'MissingCaseFish']);

    $fakePending = 'pending/audio/2025/11/01/'.$fish->id.'-missing.webm';

    $res = $this->postJson('/prefix/api/upload/audio/confirm', [
        'fish_id' => $fish->id,
        'filePath' => $fakePending,
    ]);

    $res->assertStatus(409);

    // DB should remain unchanged (no fish_audios row, fish audio_filename null)
    $this->assertDatabaseMissing('fish_audios', [
        'fish_id' => $fish->id,
        'name' => pathinfo($fakePending, PATHINFO_BASENAME),
    ]);

    $fish->refresh();
    expect($fish->audio_filename)->toBeNull();
});
