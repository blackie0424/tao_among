<?php

use App\Contracts\StorageServiceInterface;
use App\Models\Fish;
use App\Models\FishAudio;
use App\Models\FishTribeResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/** 儲存假物件：簽名網址直接回傳含路徑與有效秒數的字串，不連真 S3。 */
function fakeRecordingStorage(bool $signSucceeds = true): void
{
    test()->mock(StorageServiceInterface::class, function ($mock) use ($signSucceeds) {
        $mock->shouldIgnoreMissing();
        $mock->shouldReceive('createSignedUploadUrl')->andReturnUsing(
            fn (string $path, int $ttl) => $signSucceeds ? "https://s3.test/{$path}?ttl={$ttl}" : null
        );
    });
}

function requestRecordingUrl(?User $user, int $fishId, array $data = [])
{
    $test = $user ? test()->actingAs($user) : test();

    return $test->postJson("/fish/{$fishId}/responses/recording-url", $data);
}

// ─── AC1 資料 ─────────────────────────────────────────────

it('adds nullable recording columns to fish_tribe_responses', function () {
    expect(Schema::hasColumns('fish_tribe_responses', ['recording_filename', 'recording_submitted_at']))->toBeTrue();

    $viewer = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();

    $row = FishTribeResponse::create(['user_id' => $viewer->id, 'fish_id' => $fish->id, 'tribe' => 'yayo', 'name' => 'dalakan']);

    expect($row->fresh()->recording_filename)->toBeNull()
        ->and($row->fresh()->recording_submitted_at)->toBeNull();
});

it('keeps microsecond precision in recording_submitted_at', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();

    FishTribeResponse::create([
        'user_id' => $viewer->id, 'fish_id' => $fish->id, 'tribe' => 'yayo',
        'recording_filename' => 'a.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.123456',
    ]);

    expect(DB::table('fish_tribe_responses')->value('recording_submitted_at'))->toContain('.123456');
});

it('still allows only one response per user, fish and tribe', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    $keys = ['user_id' => $viewer->id, 'fish_id' => $fish->id, 'tribe' => 'yayo'];
    FishTribeResponse::create([...$keys, 'recording_filename' => 'a.m4a']);

    expect(fn () => FishTribeResponse::create([...$keys, 'recording_filename' => 'b.m4a']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

// ─── AC2 取得上傳網址 ─────────────────────────────────────

it('gives an activated user a 5-minute signed url inside their own pending folder', function (string $role) {
    fakeRecordingStorage();
    $user = User::factory()->create(['role' => $role]);
    $fish = Fish::factory()->create();

    $response = requestRecordingUrl($user, $fish->id)->assertOk();

    $filename = $response->json('filename');
    expect($filename)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.m4a$/')
        ->and($response->json('url'))->toBe("https://s3.test/audio-pending/{$user->id}/{$filename}?ttl=300");
})->with(['viewer', 'editor', 'admin']);

it('accepts aac and rejects any other extension', function () {
    fakeRecordingStorage();
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();

    expect(requestRecordingUrl($user, $fish->id, ['ext' => 'aac'])->assertOk()->json('filename'))->toEndWith('.aac');
    requestRecordingUrl($user, $fish->id, ['ext' => 'webm'])->assertStatus(422);
    requestRecordingUrl($user, $fish->id, ['ext' => '../x.m4a'])->assertStatus(422);
});

it('gives different folders and file names to different users', function () {
    fakeRecordingStorage();
    $a = User::factory()->create(['role' => 'viewer']);
    $b = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();

    $urlA = requestRecordingUrl($a, $fish->id)->json('url');
    $urlB = requestRecordingUrl($b, $fish->id)->json('url');

    expect($urlA)->toContain("/audio-pending/{$a->id}/")
        ->and($urlB)->toContain("/audio-pending/{$b->id}/")
        ->and($urlA)->not->toBe($urlB);
});

it('refuses guests and logged-out visitors without signing anything', function () {
    test()->mock(StorageServiceInterface::class, function ($mock) {
        $mock->shouldIgnoreMissing();
        $mock->shouldNotReceive('createSignedUploadUrl');
    });
    $guest = User::factory()->create(['role' => 'guest']);
    $fish = Fish::factory()->create();

    requestRecordingUrl($guest, $fish->id)->assertForbidden();
    requestRecordingUrl(null, $fish->id)->assertForbidden();
});

it('returns 404 for a missing or soft-deleted fish', function () {
    fakeRecordingStorage();
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();

    requestRecordingUrl($user, $fish->id + 999)->assertNotFound();

    $fish->delete();
    requestRecordingUrl($user, $fish->id)->assertNotFound();
});

it('does not write any table or touch the official fish audio', function () {
    fakeRecordingStorage();
    $user = User::factory()->create(['role' => 'editor']);
    $fish = Fish::factory()->create(['audio_filename' => null]);

    requestRecordingUrl($user, $fish->id)->assertOk();

    expect(FishTribeResponse::count())->toBe(0)
        ->and(FishAudio::count())->toBe(0)
        ->and($fish->fresh()->audio_filename)->toBeNull();
});

it('limits each user to 10 requests per minute', function () {
    fakeRecordingStorage();
    $user = User::factory()->create(['role' => 'viewer']);
    $other = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();

    foreach (range(1, 10) as $_) {
        requestRecordingUrl($user, $fish->id)->assertOk();
    }
    requestRecordingUrl($user, $fish->id)->assertStatus(429);
    requestRecordingUrl($other, $fish->id)->assertOk();
});

it('answers with an error when the storage cannot sign', function () {
    fakeRecordingStorage(signSucceeds: false);
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();

    requestRecordingUrl($user, $fish->id)->assertStatus(500)->assertJson(['message' => '無法取得錄音上傳網址，請稍後再試']);
});
