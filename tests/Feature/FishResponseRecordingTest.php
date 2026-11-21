<?php

use App\Contracts\StorageServiceInterface;
use App\Models\Fish;
use App\Models\FishAudio;
use App\Models\FishTribeResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

// ─── 步驟 2：送出、刪除與說法卡資料 ─────────────────────────

const PENDING_NAME = '11111111-1111-4111-8111-111111111111.m4a';

/**
 * 有狀態的儲存假物件：files＝路徑 => 位元組數；fail 指定哪些動作要失敗（copy、delete、delete:<路徑>）。
 */
function recordingStorage(array $files = [], array $fail = []): ArrayObject
{
    $state = new ArrayObject(['files' => $files, 'fail' => $fail], ArrayObject::ARRAY_AS_PROPS);
    test()->mock(StorageServiceInterface::class, function ($mock) use ($state) {
        $mock->shouldIgnoreMissing();
        $mock->shouldReceive('fileExists')->andReturnUsing(fn ($path) => isset($state['files'][$path]));
        $mock->shouldReceive('fileSize')->andReturnUsing(fn ($path) => $state['files'][$path] ?? null);
        $mock->shouldReceive('copyObject')->andReturnUsing(function ($from, $to) use ($state) {
            if (in_array('copy', $state['fail'], true) || ! isset($state['files'][$from])) {
                return false;
            }
            $files = $state['files'];
            $files[$to] = $files[$from];
            $state['files'] = $files;

            return true;
        });
        $mock->shouldReceive('delete')->andReturnUsing(function ($path) use ($state) {
            if (in_array('delete', $state['fail'], true) || in_array("delete:{$path}", $state['fail'], true)) {
                return false;
            }
            $files = $state['files'];
            unset($files[$path]);
            $state['files'] = $files;

            return true;
        });
        $mock->shouldReceive('getUrl')->andReturnUsing(fn ($type, $name) => "https://s3.test/{$type}/{$name}");
    });

    return $state;
}

function pendingPath(User $user, string $name = PENDING_NAME): string
{
    return "audio-pending/{$user->id}/{$name}";
}

function sendResponse(User $user, Fish $fish, array $data)
{
    return test()->actingAs($user)->post("/fish/{$fish->id}/responses", $data);
}

function removeRecording(?User $user, int $fishId, array $data = [])
{
    $test = $user ? test()->actingAs($user) : test();

    return $test->delete("/fish/{$fishId}/responses/recording", $data);
}

function recordingRow(User $user, Fish $fish, string $tribe, array $attrs = []): FishTribeResponse
{
    return FishTribeResponse::create(['user_id' => $user->id, 'fish_id' => $fish->id, 'tribe' => $tribe, ...$attrs]);
}

function responsesFor(User $user, Fish $fish): array
{
    $props = [];
    test()->actingAs($user)->get("/fish/{$fish->id}")->assertOk()
        ->assertInertia(function ($page) use (&$props) {
            $props = $page->toArray()['props']['responses'] ?? [];
        });

    return $props;
}

function tribeSummary(array $responses, string $tribe): array
{
    return collect($responses['summary'])->firstWhere('tribe', $tribe);
}

afterEach(function () {
    Carbon::setTestNow();
    FishTribeResponse::flushEventListeners();
});

// ─── AC3 送出含錄音 ───────────────────────────────────────

it('copies a valid pending recording to the response folder and stores it on the response', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    $storage = recordingStorage([pendingPath($user) => 100]);

    sendResponse($user, $fish, ['tribe' => 'yayo', 'recording' => PENDING_NAME])->assertSessionHasNoErrors();

    $row = FishTribeResponse::firstOrFail();
    expect($row->recording_filename)->toMatch('/^[0-9a-f-]{36}\.m4a$/')
        ->and($row->recording_filename)->not->toBe(PENDING_NAME)
        ->and($row->recording_submitted_at)->not->toBeNull()
        ->and($row->name)->toBeNull()
        ->and($storage['files'])->toHaveKey("response-audio/{$row->recording_filename}")
        ->and($storage['files'])->not->toHaveKey(pendingPath($user))
        ->and(FishAudio::count())->toBe(0);
});

it('gives two responses that use the same pending name different stored files', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    recordingStorage([pendingPath($user) => 100]);

    sendResponse($user, $fish, ['tribe' => 'yayo', 'recording' => PENDING_NAME])->assertSessionHasNoErrors();
    $first = FishTribeResponse::firstOrFail()->recording_filename;

    expect(FishTribeResponse::where('recording_filename', $first)->count())->toBe(1);
});

it('rejects a recording that is missing, malformed, too big, or belongs to another user', function (string $name, ?int $size, bool $mine) {
    $user = User::factory()->create(['role' => 'viewer']);
    $other = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    $owner = $mine ? $user : $other;
    $storage = recordingStorage($size === null ? [] : [pendingPath($owner, $name) => $size]);

    sendResponse($user, $fish, ['tribe' => 'yayo', 'name' => 'dalakan', 'recording' => $name])->assertSessionHasErrors('recording');

    expect(FishTribeResponse::count())->toBe(0)
        ->and(array_keys($storage['files']))->each->not->toStartWith('response-audio/');
})->with([
    'does not exist' => [PENDING_NAME, null, true],
    "someone else's file" => [PENDING_NAME, 100, false],
    'over 2 MB' => [PENDING_NAME, FishTribeResponse::RECORDING_MAX_BYTES + 1, true],
    'wrong extension' => ['11111111-1111-4111-8111-111111111111.mp3', 100, true],
    'not a uuid' => ['recording.m4a', 100, true],
]);

it('rejects a recording name that carries a path', function (string $name) {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    recordingStorage();

    sendResponse($user, $fish, ['tribe' => 'yayo', 'recording' => $name])->assertSessionHasErrors('recording');

    expect(FishTribeResponse::count())->toBe(0);
})->with(['../x.m4a', 'audio-pending/1/11111111-1111-4111-8111-111111111111.m4a', '..\\x.m4a']);

it('accepts a recording of exactly the size limit', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    recordingStorage([pendingPath($user) => FishTribeResponse::RECORDING_MAX_BYTES]);

    sendResponse($user, $fish, ['tribe' => 'yayo', 'recording' => PENDING_NAME])->assertSessionHasNoErrors();

    expect(FishTribeResponse::count())->toBe(1);
});

it('writes nothing and keeps the pending file when the copy fails, and works on retry', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    $storage = recordingStorage([pendingPath($user) => 100], ['copy']);

    sendResponse($user, $fish, ['tribe' => 'yayo', 'recording' => PENDING_NAME])
        ->assertSessionHasErrors(['recording' => '錄音儲存失敗，請重試']);
    expect(FishTribeResponse::count())->toBe(0)->and($storage['files'])->toHaveKey(pendingPath($user));

    $storage['fail'] = [];
    sendResponse($user, $fish, ['tribe' => 'yayo', 'recording' => PENDING_NAME])->assertSessionHasNoErrors();
    expect(FishTribeResponse::count())->toBe(1);
});

it('removes the copied file and keeps the old state when the database write fails', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    $storage = recordingStorage([pendingPath($user) => 100, 'response-audio/old.m4a' => 50]);
    recordingRow($user, $fish, 'yayo', ['recording_filename' => 'old.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.000001']);
    FishTribeResponse::saving(fn () => throw new RuntimeException('db down'));

    $this->withoutExceptionHandling();
    expect(fn () => sendResponse($user, $fish, ['tribe' => 'yayo', 'recording' => PENDING_NAME]))->toThrow(RuntimeException::class);

    expect(FishTribeResponse::firstOrFail()->recording_filename)->toBe('old.m4a')
        ->and(array_keys($storage['files']))->toEqualCanonicalizing(['response-audio/old.m4a', pendingPath($user)]);
});

// ─── AC4 至少一項（以合併保留後的最終狀態驗證） ───────────

it('rejects an empty response with the new message', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    recordingStorage();

    sendResponse($user, $fish, ['tribe' => 'yayo'])->assertSessionHasErrors(['name' => '叫法、分類、錄音至少填一項']);

    expect(FishTribeResponse::count())->toBe(0);
});

it('lets a response that already has a recording clear its name and category', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    recordingStorage();
    recordingRow($user, $fish, 'yayo', ['name' => 'dalakan', 'food_category' => 'oyod', 'recording_filename' => 'a.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.000001']);

    sendResponse($user, $fish, ['tribe' => 'yayo'])->assertSessionHasNoErrors();

    $row = FishTribeResponse::firstOrFail();
    expect($row->name)->toBeNull()->and($row->food_category)->toBeNull()->and($row->recording_filename)->toBe('a.m4a');
});

it('rejects clearing name and category when the response has no recording', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    recordingStorage();
    recordingRow($user, $fish, 'yayo', ['name' => 'dalakan']);

    sendResponse($user, $fish, ['tribe' => 'yayo'])->assertSessionHasErrors('name');

    expect(FishTribeResponse::firstOrFail()->name)->toBe('dalakan');
});

it('saves a recording first, then skipping the category and re-answering after reload keeps it', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    recordingStorage([pendingPath($user) => 100]);

    sendResponse($user, $fish, ['tribe' => 'yayo', 'recording' => PENDING_NAME])->assertSessionHasNoErrors();
    $stored = FishTribeResponse::firstOrFail()->recording_filename;
    sendResponse($user, $fish, ['tribe' => 'yayo', 'food_category' => 'rahet'])->assertSessionHasNoErrors();
    sendResponse($user, $fish, ['tribe' => 'yayo', 'food_category' => ''])->assertSessionHasNoErrors();

    $row = FishTribeResponse::firstOrFail();
    expect($row->recording_filename)->toBe($stored)->and($row->food_category)->toBeNull();
});

// ─── AC5 重錄與保留 ───────────────────────────────────────

it('keeps the recording and its time when a later submit carries no recording', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    recordingStorage();
    recordingRow($user, $fish, 'yayo', ['name' => 'dalakan', 'recording_filename' => 'a.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.123456']);

    sendResponse($user, $fish, ['tribe' => 'yayo', 'name' => 'dalakan', 'food_category' => 'oyod'])->assertSessionHasNoErrors();

    $row = FishTribeResponse::firstOrFail();
    expect($row->recording_filename)->toBe('a.m4a')
        ->and(DB::table('fish_tribe_responses')->value('recording_submitted_at'))->toContain('2026-11-21 10:00:00.123456');
});

it('replaces the recording, updates its time and removes the old file', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    $storage = recordingStorage([pendingPath($user) => 100, 'response-audio/old.m4a' => 50]);
    recordingRow($user, $fish, 'yayo', ['recording_filename' => 'old.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.000000']);
    Carbon::setTestNow(Carbon::parse('2026-11-21 12:00:00'));

    sendResponse($user, $fish, ['tribe' => 'yayo', 'recording' => PENDING_NAME])->assertSessionHasNoErrors();

    $row = FishTribeResponse::firstOrFail();
    expect($row->recording_filename)->not->toBe('old.m4a')
        ->and($row->recording_submitted_at)->toStartWith('2026-11-21 12:00:00')
        ->and($storage['files'])->not->toHaveKey('response-audio/old.m4a')
        ->and($storage['files'])->toHaveKey("response-audio/{$row->recording_filename}");
});

it('still succeeds, points to the new file and logs when the old file cannot be removed', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    $storage = recordingStorage([pendingPath($user) => 100, 'response-audio/old.m4a' => 50], ['delete:response-audio/old.m4a']);
    recordingRow($user, $fish, 'yayo', ['recording_filename' => 'old.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.000000']);
    Log::spy();

    sendResponse($user, $fish, ['tribe' => 'yayo', 'recording' => PENDING_NAME])->assertSessionHasNoErrors();

    expect(FishTribeResponse::firstOrFail()->recording_filename)->not->toBe('old.m4a')
        ->and($storage['files'])->toHaveKey('response-audio/old.m4a');
    Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context = []) => str_contains($message, '[response-recording]') && ($context['filename'] ?? null) === 'old.m4a');
});

// ─── AC6 作者刪除 ─────────────────────────────────────────

it('deletes only the author recording of that tribe and keeps the rest of the response', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    $other = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    $storage = recordingStorage(['response-audio/a.m4a' => 1, 'response-audio/b.m4a' => 1, 'response-audio/c.m4a' => 1]);
    recordingRow($user, $fish, 'yayo', ['name' => 'dalakan', 'recording_filename' => 'a.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.000001']);
    recordingRow($user, $fish, 'imowrod', ['name' => 'rakan', 'recording_filename' => 'b.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.000002']);
    recordingRow($other, $fish, 'yayo', ['name' => 'dalakan', 'recording_filename' => 'c.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.000003']);

    removeRecording($user, $fish->id, ['tribe' => 'yayo', 'user_id' => $other->id])->assertSessionHasNoErrors();

    $mine = FishTribeResponse::where('user_id', $user->id)->where('tribe', 'yayo')->firstOrFail();
    expect($mine->name)->toBe('dalakan')->and($mine->recording_filename)->toBeNull()->and($mine->recording_submitted_at)->toBeNull()
        ->and(FishTribeResponse::where('user_id', $user->id)->where('tribe', 'imowrod')->firstOrFail()->recording_filename)->toBe('b.m4a')
        ->and(FishTribeResponse::where('user_id', $other->id)->firstOrFail()->recording_filename)->toBe('c.m4a')
        ->and(array_keys($storage['files']))->toEqualCanonicalizing(['response-audio/b.m4a', 'response-audio/c.m4a']);
});

it('deletes the whole response when nothing is left after removing the recording', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    recordingStorage(['response-audio/a.m4a' => 1]);
    recordingRow($user, $fish, 'yayo', ['recording_filename' => 'a.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.000001']);

    removeRecording($user, $fish->id, ['tribe' => 'yayo'])->assertSessionHasNoErrors();

    expect(FishTribeResponse::count())->toBe(0);
});

it('keeps the data and can be retried when the file cannot be deleted', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    $storage = recordingStorage(['response-audio/a.m4a' => 1], ['delete']);
    recordingRow($user, $fish, 'yayo', ['name' => 'dalakan', 'recording_filename' => 'a.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.000001']);

    removeRecording($user, $fish->id, ['tribe' => 'yayo'])->assertSessionHasErrors(['recording' => '刪除失敗，請重試']);
    expect(FishTribeResponse::firstOrFail()->recording_filename)->toBe('a.m4a')->and($storage['files'])->toHaveKey('response-audio/a.m4a');

    $storage['fail'] = [];
    removeRecording($user, $fish->id, ['tribe' => 'yayo'])->assertSessionHasNoErrors();
    expect(FishTribeResponse::firstOrFail()->recording_filename)->toBeNull();
});

it('refuses guests and logged-out visitors from deleting, and 404s for a soft-deleted fish', function () {
    $guest = User::factory()->create(['role' => 'guest']);
    $viewer = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    recordingStorage(['response-audio/a.m4a' => 1]);
    recordingRow($viewer, $fish, 'yayo', ['recording_filename' => 'a.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.000001']);

    removeRecording($guest, $fish->id, ['tribe' => 'yayo'])->assertForbidden();
    removeRecording(null, $fish->id, ['tribe' => 'yayo'])->assertForbidden();
    expect(FishTribeResponse::firstOrFail()->recording_filename)->toBe('a.m4a');

    $fish->delete();
    removeRecording($viewer, $fish->id, ['tribe' => 'yayo'])->assertNotFound();
});

// ─── AC7 可見範圍 ─────────────────────────────────────────

it('gives viewer, editor and admin the recording urls and keeps them away from guests', function (string $role) {
    $author = User::factory()->create(['role' => 'viewer']);
    $reader = User::factory()->create(['role' => $role]);
    $guest = User::factory()->create(['role' => 'guest']);
    $fish = Fish::factory()->create();
    recordingStorage();
    recordingRow($author, $fish, 'yayo', ['name' => 'dalakan', 'recording_filename' => 'secret-a.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.000001']);

    $names = tribeSummary(responsesFor($reader, $fish), 'yayo')['names'];
    expect($names[0]['recording_url'])->toBe('https://s3.test/response_audio/secret-a.m4a');

    test()->actingAs($guest)->get("/fish/{$fish->id}")->assertForbidden()->assertDontSee('secret-a');
    test()->actingAs($guest)->post("/fish/{$fish->id}/responses", ['tribe' => 'yayo', 'name' => 'x'])->assertForbidden()->assertDontSee('secret-a');
    auth()->logout();
    test()->get("/fish/{$fish->id}")->assertDontSee('secret-a');
})->with(['viewer', 'editor', 'admin']);

it('gives the author their own recording url in mine', function () {
    $author = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    recordingStorage();
    recordingRow($author, $fish, 'yayo', ['recording_filename' => 'mine.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.000001']);
    recordingRow($author, $fish, 'imowrod', ['name' => 'rakan']);

    $mine = collect(responsesFor($author, $fish)['mine'])->keyBy('tribe');

    expect($mine['yayo']['recording_url'])->toBe('https://s3.test/response_audio/mine.m4a')
        ->and($mine['imowrod']['recording_url'])->toBeNull();
});

// ─── AC8 說法卡資料 ───────────────────────────────────────

it('attaches the earliest recording of a spelling group and leaves recording-less groups without one', function () {
    [$u1, $u2, $u3, $u4] = User::factory()->count(4)->create(['role' => 'viewer'])->all();
    $fish = Fish::factory()->create();
    recordingStorage();
    Carbon::setTestNow(Carbon::parse('2026-11-21 09:00:00'));
    recordingRow($u1, $fish, 'yayo', ['name' => 'dalakan', 'name_submitted_at' => '2026-11-21 09:00:00.000001', 'recording_filename' => 'late.m4a', 'recording_submitted_at' => '2026-11-21 11:00:00.000000']);
    recordingRow($u2, $fish, 'yayo', ['name' => ' Dalakan', 'name_submitted_at' => '2026-11-21 09:00:00.000002', 'recording_filename' => 'early.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.000000']);
    recordingRow($u3, $fish, 'yayo', ['name' => 'rakan', 'name_submitted_at' => '2026-11-21 09:00:00.000003']);
    recordingRow($u4, $fish, 'yayo', ['name' => 'rakan', 'name_submitted_at' => '2026-11-21 09:00:00.000004']);

    $names = tribeSummary(responsesFor($u1, $fish), 'yayo')['names'];

    expect($names)->toBe([
        ['name' => 'dalakan', 'count' => 2, 'recording_url' => 'https://s3.test/response_audio/early.m4a'],
        ['name' => 'rakan', 'count' => 2],
    ]);
});

it('breaks ties on recording time by response id', function () {
    [$u1, $u2] = User::factory()->count(2)->create(['role' => 'viewer'])->all();
    $fish = Fish::factory()->create();
    recordingStorage();
    $same = '2026-11-21 10:00:00.500000';
    recordingRow($u1, $fish, 'yayo', ['name' => 'dalakan', 'name_submitted_at' => $same, 'recording_filename' => 'first.m4a', 'recording_submitted_at' => $same]);
    recordingRow($u2, $fish, 'yayo', ['name' => 'dalakan', 'name_submitted_at' => $same, 'recording_filename' => 'second.m4a', 'recording_submitted_at' => $same]);

    $summary = tribeSummary(responsesFor($u1, $fish), 'yayo');

    expect($summary['names'][0]['recording_url'])->toBe('https://s3.test/response_audio/first.m4a');
});

it('lists recording-only responses of a tribe in recording order and counts them as a response', function () {
    [$u1, $u2, $u3] = User::factory()->count(3)->create(['role' => 'viewer'])->all();
    $fish = Fish::factory()->create();
    recordingStorage();
    recordingRow($u1, $fish, 'imowrod', ['recording_filename' => 'second.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.000002']);
    recordingRow($u2, $fish, 'imowrod', ['recording_filename' => 'first.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.000001', 'food_category' => 'oyod']);
    recordingRow($u3, $fish, 'imowrod', ['name' => 'rakan', 'name_submitted_at' => '2026-11-21 09:00:00.000001']);

    $responses = responsesFor($u1, $fish);
    $summary = tribeSummary($responses, 'imowrod');

    expect($summary['has_responses'])->toBeTrue()
        ->and($summary['recordings_only'])->toBe([
            ['recording_url' => 'https://s3.test/response_audio/first.m4a'],
            ['recording_url' => 'https://s3.test/response_audio/second.m4a'],
        ])
        ->and($summary['names'])->toBe([['name' => 'rakan', 'count' => 1]])
        ->and($summary['has_variants'])->toBeFalse()
        ->and($responses['responders'])->toBe(3)
        ->and(tribeSummary($responses, 'yayo')['recordings_only'])->toBe([]);
});

it('counts a recording-only responder once even with recordings in two tribes', function () {
    $u1 = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    recordingStorage();
    recordingRow($u1, $fish, 'imowrod', ['recording_filename' => 'a.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.000001']);
    recordingRow($u1, $fish, 'yayo', ['recording_filename' => 'b.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.000002']);

    expect(responsesFor($u1, $fish)['responders'])->toBe(1);
});
