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

    sendResponse($user, $fish, ['tribe' => 'yayo', 'recording' => PENDING_NAME, 'recording_seconds' => 12])->assertSessionHasNoErrors();

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

    sendResponse($user, $fish, ['tribe' => 'yayo', 'recording' => PENDING_NAME, 'recording_seconds' => 12])->assertSessionHasNoErrors();
    $first = FishTribeResponse::firstOrFail()->recording_filename;

    expect(FishTribeResponse::where('recording_filename', $first)->count())->toBe(1);
});

it('rejects a recording that is missing, malformed, too big, or belongs to another user', function (string $name, ?int $size, bool $mine) {
    $user = User::factory()->create(['role' => 'viewer']);
    $other = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    $owner = $mine ? $user : $other;
    $storage = recordingStorage($size === null ? [] : [pendingPath($owner, $name) => $size]);

    sendResponse($user, $fish, ['tribe' => 'yayo', 'name' => 'dalakan', 'recording' => $name, 'recording_seconds' => 12])->assertSessionHasErrors('recording');

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

    sendResponse($user, $fish, ['tribe' => 'yayo', 'recording' => $name, 'recording_seconds' => 12])->assertSessionHasErrors('recording');

    expect(FishTribeResponse::count())->toBe(0);
})->with(['../x.m4a', 'audio-pending/1/11111111-1111-4111-8111-111111111111.m4a', '..\\x.m4a']);

it('deletes an oversize pending file when rejecting it, and still rejects when the delete fails', function (array $fail) {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    $storage = recordingStorage([pendingPath($user) => FishTribeResponse::RECORDING_MAX_BYTES + 1], $fail);
    Log::spy();

    sendResponse($user, $fish, ['tribe' => 'yayo', 'recording' => PENDING_NAME, 'recording_seconds' => 12])->assertSessionHasErrors('recording');

    expect(FishTribeResponse::count())->toBe(0)
        ->and(isset($storage['files'][pendingPath($user)]))->toBe($fail !== []);
    if ($fail !== []) {
        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context = []) => str_contains($message, '[response-recording]') && ($context['filename'] ?? null) === PENDING_NAME);
    }
})->with(['delete works' => [[]], 'delete fails' => [['delete']]]);

it('accepts a recording of exactly the size limit', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    recordingStorage([pendingPath($user) => FishTribeResponse::RECORDING_MAX_BYTES]);

    sendResponse($user, $fish, ['tribe' => 'yayo', 'recording' => PENDING_NAME, 'recording_seconds' => 12])->assertSessionHasNoErrors();

    expect(FishTribeResponse::count())->toBe(1);
});

it('writes nothing and keeps the pending file when the copy fails, and works on retry', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    $storage = recordingStorage([pendingPath($user) => 100], ['copy']);

    sendResponse($user, $fish, ['tribe' => 'yayo', 'recording' => PENDING_NAME, 'recording_seconds' => 12])
        ->assertSessionHasErrors(['recording' => '錄音儲存失敗，請重試']);
    expect(FishTribeResponse::count())->toBe(0)->and($storage['files'])->toHaveKey(pendingPath($user));

    $storage['fail'] = [];
    sendResponse($user, $fish, ['tribe' => 'yayo', 'recording' => PENDING_NAME, 'recording_seconds' => 12])->assertSessionHasNoErrors();
    expect(FishTribeResponse::count())->toBe(1);
});

it('removes the copied file and keeps the old state when the database write fails', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    $storage = recordingStorage([pendingPath($user) => 100, 'response-audio/old.m4a' => 50]);
    recordingRow($user, $fish, 'yayo', ['recording_filename' => 'old.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.000001']);
    FishTribeResponse::saving(fn () => throw new RuntimeException('db down'));

    $this->withoutExceptionHandling();
    expect(fn () => sendResponse($user, $fish, ['tribe' => 'yayo', 'recording' => PENDING_NAME, 'recording_seconds' => 12]))->toThrow(RuntimeException::class);

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

    sendResponse($user, $fish, ['tribe' => 'yayo', 'recording' => PENDING_NAME, 'recording_seconds' => 12])->assertSessionHasNoErrors();
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

    sendResponse($user, $fish, ['tribe' => 'yayo', 'recording' => PENDING_NAME, 'recording_seconds' => 12])->assertSessionHasNoErrors();

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

    sendResponse($user, $fish, ['tribe' => 'yayo', 'recording' => PENDING_NAME, 'recording_seconds' => 12])->assertSessionHasNoErrors();

    expect(FishTribeResponse::firstOrFail()->recording_filename)->not->toBe('old.m4a')
        ->and($storage['files'])->toHaveKey('response-audio/old.m4a');
    Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context = []) => str_contains($message, '[response-recording]') && ($context['filename'] ?? null) === 'old.m4a');
});

it('still completes a re-record when the author deleted the recording-only response in between', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    $storage = recordingStorage([pendingPath($user) => 100, 'response-audio/old.m4a' => 50]);
    recordingRow($user, $fish, 'yayo', ['recording_filename' => 'old.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.000001']);
    // 重錄的新增因唯一鍵被略過後，刪除先拿到鎖、刪掉整筆
    $raced = false;
    DB::listen(function ($query) use (&$raced) {
        if (! $raced && str_contains(strtolower($query->sql), 'ignore')) {
            $raced = true;
            DB::table('fish_tribe_responses')->delete();
        }
    });

    sendResponse($user, $fish, ['tribe' => 'yayo', 'recording' => PENDING_NAME, 'recording_seconds' => 12])->assertSessionHasNoErrors();

    $row = FishTribeResponse::firstOrFail();
    expect($raced)->toBeTrue()
        ->and($row->recording_filename)->not->toBe('old.m4a')
        ->and($storage['files'])->toHaveKey("response-audio/{$row->recording_filename}");
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

const LEAK_MARKERS = ['secret-a', 'secret-b', 'response_audio', 'recording_url', 'recording_filename'];

function assertNoRecordingLeak($response): void
{
    foreach (LEAK_MARKERS as $marker) {
        expect($response->getContent())->not->toContain($marker);
    }
}

it('gives viewer, editor and admin exactly the whitelisted response data including recording urls', function (string $role) {
    $author = User::factory()->create(['role' => 'viewer']);
    $reader = User::factory()->create(['role' => $role]);
    $fish = Fish::factory()->create();
    recordingStorage();
    recordingRow($author, $fish, 'yayo', ['name' => 'dalakan', 'name_submitted_at' => '2026-11-21 09:00:00.000001', 'recording_filename' => 'secret-a.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.000001']);
    recordingRow($author, $fish, 'imowrod', ['recording_filename' => 'secret-b.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.000002']);
    recordingRow($reader, $fish, 'iraraley', ['name' => 'rakan', 'recording_filename' => 'secret-b.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.000003']);

    $responses = responsesFor($reader, $fish);

    expect(array_keys($responses))->toBe(['form', 'summary', 'mine', 'responders'])
        ->and(array_keys($responses['summary'][0]))->toBe(['tribe', 'has_responses', 'names', 'recordings_only', 'has_variants', 'categories'])
        ->and(array_keys(tribeSummary($responses, 'yayo')['names'][0]))->toBe(['name', 'count', 'recordings'])
        ->and(array_keys(tribeSummary($responses, 'yayo')['names'][0]['recordings'][0]))->toBe(['recording_url', 'recording_seconds'])
        ->and(array_keys(tribeSummary($responses, 'imowrod')['recordings_only'][0]))->toBe(['recording_url', 'recording_seconds'])
        ->and(array_keys($responses['mine'][0]))->toBe(['tribe', 'name', 'food_category', 'recording_url', 'recording_seconds'])
        ->and(tribeSummary($responses, 'yayo')['names'][0]['recordings'][0]['recording_url'])->toBe('https://s3.test/response_audio/secret-a.m4a')
        ->and(tribeSummary($responses, 'imowrod')['recordings_only'][0]['recording_url'])->toBe('https://s3.test/response_audio/secret-b.m4a')
        ->and($responses['mine'][0]['recording_url'])->toBe('https://s3.test/response_audio/secret-b.m4a')
        ->and(json_encode($responses))->not->toContain('recording_filename');
})->with(['viewer', 'editor', 'admin']);

it('keeps every recording out of every response for guests and logged-out visitors', function (?string $role) {
    $author = User::factory()->create(['role' => 'viewer']);
    $visitor = $role === null ? null : User::factory()->create(['role' => $role]);
    $fish = Fish::factory()->create();
    recordingStorage([pendingPath($author) => 100]);
    recordingRow($author, $fish, 'yayo', ['name' => 'dalakan', 'recording_filename' => 'secret-a.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.000001']);
    $as = fn () => $visitor ? test()->actingAs($visitor) : test();
    $inertia = ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'];

    assertNoRecordingLeak($as()->get("/fish/{$fish->id}"));
    assertNoRecordingLeak($as()->get("/fish/{$fish->id}", $inertia));
    assertNoRecordingLeak($as()->get("/fish/{$fish->id}"));
    assertNoRecordingLeak($as()->post("/fish/{$fish->id}/responses", ['tribe' => 'yayo', 'name' => 'x']));
    assertNoRecordingLeak($as()->post("/fish/{$fish->id}/responses", ['tribe' => 'nowhere', 'recording' => '../x'], $inertia));
    assertNoRecordingLeak($as()->postJson("/fish/{$fish->id}/responses/recording-url"));
    assertNoRecordingLeak($as()->delete("/fish/{$fish->id}/responses/recording", ['tribe' => 'yayo']));

    expect(FishTribeResponse::count())->toBe(1)->and(FishTribeResponse::firstOrFail()->name)->toBe('dalakan');
})->with(['guest', 'logged out' => [null]]);

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

it('lists the recordings of others in a spelling group and gives recording-less groups an empty list', function () {
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
        ['name' => 'dalakan', 'count' => 2, 'recordings' => [['recording_url' => 'https://s3.test/response_audio/early.m4a', 'recording_seconds' => null]]],
        ['name' => 'rakan', 'count' => 2, 'recordings' => []],
    ]);
});

it('breaks ties on recording time by response id', function () {
    [$u1, $u2] = User::factory()->count(2)->create(['role' => 'viewer'])->all();
    $fish = Fish::factory()->create();
    recordingStorage();
    $same = '2026-11-21 10:00:00.500000';
    recordingRow($u1, $fish, 'yayo', ['name' => 'dalakan', 'name_submitted_at' => $same, 'recording_filename' => 'first.m4a', 'recording_submitted_at' => $same]);
    recordingRow($u2, $fish, 'yayo', ['name' => 'dalakan', 'name_submitted_at' => $same, 'recording_filename' => 'second.m4a', 'recording_submitted_at' => $same]);

    $viewer = User::factory()->create(['role' => 'viewer']);
    $summary = tribeSummary(responsesFor($viewer, $fish), 'yayo');

    expect($summary['names'][0]['recordings'][0]['recording_url'])->toBe('https://s3.test/response_audio/first.m4a');
});

it('lists recording-only responses of a tribe in recording order and counts them as a response', function () {
    [$u1, $u2, $u3] = User::factory()->count(3)->create(['role' => 'viewer'])->all();
    $fish = Fish::factory()->create();
    recordingStorage();
    recordingRow($u1, $fish, 'imowrod', ['recording_filename' => 'second.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.000002']);
    recordingRow($u2, $fish, 'imowrod', ['recording_filename' => 'first.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.000001', 'food_category' => 'oyod']);
    recordingRow($u3, $fish, 'imowrod', ['name' => 'rakan', 'name_submitted_at' => '2026-11-21 09:00:00.000001']);

    $viewer = User::factory()->create(['role' => 'viewer']);
    $responses = responsesFor($viewer, $fish);
    $summary = tribeSummary($responses, 'imowrod');

    expect($summary['has_responses'])->toBeTrue()
        ->and($summary['recordings_only'])->toBe([
            ['recording_url' => 'https://s3.test/response_audio/first.m4a', 'recording_seconds' => null],
            ['recording_url' => 'https://s3.test/response_audio/second.m4a', 'recording_seconds' => null],
        ])
        ->and($summary['names'])->toBe([['name' => 'rakan', 'count' => 1, 'recordings' => []]])
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

// ─── 修訂 4：錄音秒數 ─────────────────────────────────────

const SECONDS_ERROR = '錄音秒數不正確，請重錄';

it('adds a nullable recording_seconds column and exposes the shared maximum to the form', function () {
    expect(Schema::hasColumn('fish_tribe_responses', 'recording_seconds'))->toBeTrue();
    expect(FishTribeResponse::RECORDING_MAX_SECONDS)->toBe(30);

    $viewer = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    recordingStorage();
    expect(responsesFor($viewer, $fish)['form']['recording_max_seconds'])->toBe(30);
});

it('stores the recording seconds together with the recording', function (int $seconds) {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    recordingStorage([pendingPath($user) => 100]);

    sendResponse($user, $fish, ['tribe' => 'yayo', 'recording' => PENDING_NAME, 'recording_seconds' => $seconds])->assertSessionHasNoErrors();

    expect(FishTribeResponse::firstOrFail()->recording_seconds)->toBe($seconds);
})->with([1, 12, 30]);

it('rejects missing, out-of-range or non-integer seconds and keeps the pending file', function (mixed $seconds) {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    $storage = recordingStorage([pendingPath($user) => 100]);

    sendResponse($user, $fish, ['tribe' => 'yayo', 'name' => 'dalakan', 'recording' => PENDING_NAME, 'recording_seconds' => $seconds])
        ->assertSessionHasErrors(['recording_seconds' => SECONDS_ERROR]);

    expect(FishTribeResponse::count())->toBe(0)
        ->and(array_keys($storage['files']))->toBe([pendingPath($user)]);
})->with(['missing' => [null], 'zero' => [0], 'over the limit' => [31], 'decimal' => ['1.5'], 'text' => ['abc'], 'negative' => [-3]]);

it('rejects seconds sent without a recording', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    recordingStorage();

    sendResponse($user, $fish, ['tribe' => 'yayo', 'name' => 'dalakan', 'recording_seconds' => 12])
        ->assertSessionHasErrors(['recording_seconds' => '沒有錄音時不能帶錄音秒數']);

    expect(FishTribeResponse::count())->toBe(0);
});

it('validates fields before touching storage: bad seconds and a huge file answers only the seconds error and keeps the file', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    $storage = recordingStorage([pendingPath($user) => FishTribeResponse::RECORDING_MAX_BYTES + 1]);

    $response = sendResponse($user, $fish, ['tribe' => 'yayo', 'recording' => PENDING_NAME, 'recording_seconds' => 99]);

    $response->assertSessionHasErrors(['recording_seconds' => SECONDS_ERROR])->assertSessionDoesntHaveErrors('recording');
    expect($storage['files'])->toHaveKey(pendingPath($user));
});

it('validates fields before touching storage: bad seconds and a missing file answers only the seconds error', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    recordingStorage();

    sendResponse($user, $fish, ['tribe' => 'yayo', 'recording' => PENDING_NAME, 'recording_seconds' => 0])
        ->assertSessionHasErrors(['recording_seconds' => SECONDS_ERROR])
        ->assertSessionDoesntHaveErrors('recording');
});

it('reports a malformed file name and bad seconds together without touching storage', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    recordingStorage();

    sendResponse($user, $fish, ['tribe' => 'yayo', 'recording' => '../x.m4a', 'recording_seconds' => 0])
        ->assertSessionHasErrors(['recording', 'recording_seconds']);
});

it('keeps the old seconds when a later submit carries no recording, and an old recording without seconds stays empty', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    recordingStorage();
    recordingRow($user, $fish, 'yayo', ['name' => 'dalakan', 'recording_filename' => 'new.m4a', 'recording_seconds' => 9, 'recording_submitted_at' => '2026-11-21 10:00:00.000001']);
    recordingRow($user, $fish, 'imowrod', ['name' => 'rakan', 'recording_filename' => 'old.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.000002']);

    sendResponse($user, $fish, ['tribe' => 'yayo', 'name' => 'dalakan', 'food_category' => 'oyod'])->assertSessionHasNoErrors();
    sendResponse($user, $fish, ['tribe' => 'imowrod', 'name' => 'rakan', 'food_category' => 'oyod'])->assertSessionHasNoErrors();

    $mine = collect(responsesFor($user, $fish)['mine'])->keyBy('tribe');
    expect($mine['yayo']['recording_seconds'])->toBe(9)
        ->and($mine['imowrod']['recording_url'])->not->toBeNull()
        ->and($mine['imowrod']['recording_seconds'])->toBeNull();
});

it('updates the seconds when the recording is replaced, and clears them when the recording is deleted', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    recordingStorage([pendingPath($user) => 100, 'response-audio/old.m4a' => 50]);
    recordingRow($user, $fish, 'yayo', ['name' => 'dalakan', 'recording_filename' => 'old.m4a', 'recording_seconds' => 5, 'recording_submitted_at' => '2026-11-21 10:00:00.000001']);

    sendResponse($user, $fish, ['tribe' => 'yayo', 'name' => 'dalakan', 'recording' => PENDING_NAME, 'recording_seconds' => 21])->assertSessionHasNoErrors();
    expect(FishTribeResponse::firstOrFail()->recording_seconds)->toBe(21);

    removeRecording($user, $fish->id, ['tribe' => 'yayo'])->assertSessionHasNoErrors();
    $row = FishTribeResponse::firstOrFail();
    expect($row->recording_filename)->toBeNull()->and($row->recording_seconds)->toBeNull();
});

it('gives the seconds of the earliest recording of a spelling group, of recording-only entries and of mine', function () {
    [$u1, $u2, $u3] = User::factory()->count(3)->create(['role' => 'viewer'])->all();
    $fish = Fish::factory()->create();
    recordingStorage();
    recordingRow($u1, $fish, 'yayo', ['name' => 'dalakan', 'name_submitted_at' => '2026-11-21 09:00:00.000001', 'recording_filename' => 'late.m4a', 'recording_seconds' => 20, 'recording_submitted_at' => '2026-11-21 11:00:00.000000']);
    recordingRow($u2, $fish, 'yayo', ['name' => 'dalakan', 'name_submitted_at' => '2026-11-21 09:00:00.000002', 'recording_filename' => 'early.m4a', 'recording_seconds' => 7, 'recording_submitted_at' => '2026-11-21 10:00:00.000000']);
    recordingRow($u3, $fish, 'yayo', ['recording_filename' => 'only.m4a', 'recording_seconds' => 4, 'recording_submitted_at' => '2026-11-21 10:30:00.000000']);

    $responses = responsesFor($u1, $fish);
    $summary = tribeSummary($responses, 'yayo');

    expect($summary['names'][0]['recordings'][0]['recording_seconds'])->toBe(7)
        ->and($summary['recordings_only'][0]['recording_seconds'])->toBe(4)
        ->and($responses['mine'][0]['recording_seconds'])->toBe(20);
});

// ─── 修訂 5：說法卡的錄音排除查看者自己的 ───────────────────

it('shows the viewer the recordings of others in the same spelling group, never their own', function () {
    [$me, $other, $third] = User::factory()->count(3)->create(['role' => 'viewer'])->all();
    $fish = Fish::factory()->create();
    recordingStorage();
    recordingRow($me, $fish, 'yayo', ['name' => 'dalakan', 'name_submitted_at' => '2026-11-21 09:00:00.000001', 'recording_filename' => 'mine-early.m4a', 'recording_seconds' => 10, 'recording_submitted_at' => '2026-11-21 10:00:00.000000']);
    recordingRow($other, $fish, 'yayo', ['name' => 'dalakan', 'name_submitted_at' => '2026-11-21 09:00:00.000002', 'recording_filename' => 'other-late.m4a', 'recording_seconds' => 9, 'recording_submitted_at' => '2026-11-21 11:00:00.000000']);
    recordingRow($third, $fish, 'yayo', ['name' => 'dalakan', 'name_submitted_at' => '2026-11-21 09:00:00.000003', 'recording_filename' => 'third-latest.m4a', 'recording_seconds' => 14, 'recording_submitted_at' => '2026-11-21 12:00:00.000000']);

    $mine = tribeSummary(responsesFor($me, $fish), 'yayo')['names'][0];
    $theirs = tribeSummary(responsesFor($other, $fish), 'yayo')['names'][0];

    expect($mine['count'])->toBe(3)
        ->and(array_column($mine['recordings'], 'recording_url'))->toBe(['https://s3.test/response_audio/other-late.m4a', 'https://s3.test/response_audio/third-latest.m4a'])
        ->and(array_column($mine['recordings'], 'recording_seconds'))->toBe([9, 14])
        ->and($theirs['count'])->toBe(3)
        ->and(array_column($theirs['recordings'], 'recording_url'))->toBe(['https://s3.test/response_audio/mine-early.m4a', 'https://s3.test/response_audio/third-latest.m4a']);
});

it('gives an empty recordings list when the only recording is the viewer own, but keeps it in mine', function () {
    [$me, $other] = User::factory()->count(2)->create(['role' => 'viewer'])->all();
    $fish = Fish::factory()->create();
    recordingStorage();
    recordingRow($me, $fish, 'yayo', ['name' => 'dalakan', 'recording_filename' => 'mine.m4a', 'recording_seconds' => 10, 'recording_submitted_at' => '2026-11-21 10:00:00.000000']);
    recordingRow($other, $fish, 'yayo', ['name' => 'dalakan']);

    $responses = responsesFor($me, $fish);

    expect(tribeSummary($responses, 'yayo')['names'])->toBe([['name' => 'dalakan', 'count' => 2, 'recordings' => []]])
        ->and($responses['mine'][0]['recording_url'])->toBe('https://s3.test/response_audio/mine.m4a')
        ->and(array_column(tribeSummary(responsesFor($other, $fish), 'yayo')['names'][0]['recordings'], 'recording_url'))->toBe(['https://s3.test/response_audio/mine.m4a']);
});

it('lists only the recordings of others as recording-only entries, and still counts the viewer', function () {
    [$me, $a, $b] = User::factory()->count(3)->create(['role' => 'viewer'])->all();
    $fish = Fish::factory()->create();
    recordingStorage();
    recordingRow($me, $fish, 'imowrod', ['recording_filename' => 'mine.m4a', 'recording_seconds' => 3, 'recording_submitted_at' => '2026-11-21 10:00:00.000001']);
    recordingRow($a, $fish, 'imowrod', ['recording_filename' => 'a.m4a', 'recording_seconds' => 5, 'recording_submitted_at' => '2026-11-21 10:00:00.000002']);
    recordingRow($b, $fish, 'imowrod', ['recording_filename' => 'b.m4a', 'recording_seconds' => 8, 'recording_submitted_at' => '2026-11-21 10:00:00.000003']);

    $responses = responsesFor($me, $fish);
    $summary = tribeSummary($responses, 'imowrod');

    expect($summary['recordings_only'])->toBe([
        ['recording_url' => 'https://s3.test/response_audio/a.m4a', 'recording_seconds' => 5],
        ['recording_url' => 'https://s3.test/response_audio/b.m4a', 'recording_seconds' => 8],
    ])->and($summary['has_responses'])->toBeTrue()
        ->and($responses['responders'])->toBe(3);
});

it('keeps a tribe with only the viewer recording-only response as a responded tribe with nothing to list', function () {
    $me = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    recordingStorage();
    recordingRow($me, $fish, 'imowrod', ['recording_filename' => 'mine.m4a', 'recording_seconds' => 3, 'recording_submitted_at' => '2026-11-21 10:00:00.000001']);

    $summary = tribeSummary(responsesFor($me, $fish), 'imowrod');

    expect($summary['has_responses'])->toBeTrue()
        ->and($summary['names'])->toBe([])
        ->and($summary['recordings_only'])->toBe([]);
});

// ─── #166：同一種寫法的多段錄音 ──────────────────────────────

it('lists every recording of others in one spelling group, earliest first', function () {
    [$ming, $hua, $mei, $viewer] = User::factory()->count(4)->create(['role' => 'viewer'])->all();
    $fish = Fish::factory()->create();
    recordingStorage();
    recordingRow($ming, $fish, 'yayo', ['name' => 'dalakan', 'name_submitted_at' => '2026-11-21 09:00:00.000001', 'recording_filename' => 'ming.m4a', 'recording_seconds' => 9, 'recording_submitted_at' => '2026-11-21 10:00:00.000000']);
    recordingRow($hua, $fish, 'yayo', ['name' => 'dalakan', 'name_submitted_at' => '2026-11-21 09:00:00.000002', 'recording_filename' => 'hua.m4a', 'recording_seconds' => 14, 'recording_submitted_at' => '2026-11-21 11:00:00.000000']);
    recordingRow($mei, $fish, 'yayo', ['name' => 'dalakan', 'name_submitted_at' => '2026-11-21 09:00:00.000003', 'recording_filename' => 'mei.m4a', 'recording_seconds' => 6, 'recording_submitted_at' => '2026-11-21 12:00:00.000000']);

    $group = tribeSummary(responsesFor($viewer, $fish), 'yayo')['names'][0];

    expect($group)->toBe(['name' => 'dalakan', 'count' => 3, 'recordings' => [
        ['recording_url' => 'https://s3.test/response_audio/ming.m4a', 'recording_seconds' => 9],
        ['recording_url' => 'https://s3.test/response_audio/hua.m4a', 'recording_seconds' => 14],
        ['recording_url' => 'https://s3.test/response_audio/mei.m4a', 'recording_seconds' => 6],
    ]]);
});

it('shows each viewer only the recordings of others in the group, never their own', function () {
    [$ming, $hua, $mei] = User::factory()->count(3)->create(['role' => 'viewer'])->all();
    $fish = Fish::factory()->create();
    recordingStorage();
    recordingRow($ming, $fish, 'yayo', ['name' => 'dalakan', 'recording_filename' => 'ming.m4a', 'recording_seconds' => 9, 'recording_submitted_at' => '2026-11-21 10:00:00.000000']);
    recordingRow($hua, $fish, 'yayo', ['name' => 'dalakan', 'recording_filename' => 'hua.m4a', 'recording_seconds' => 14, 'recording_submitted_at' => '2026-11-21 11:00:00.000000']);
    recordingRow($mei, $fish, 'yayo', ['name' => 'dalakan', 'recording_filename' => 'mei.m4a', 'recording_seconds' => 6, 'recording_submitted_at' => '2026-11-21 12:00:00.000000']);
    $urlsFor = fn (User $user) => array_column(tribeSummary(responsesFor($user, $fish), 'yayo')['names'][0]['recordings'], 'recording_url');

    expect($urlsFor($ming))->toBe(['https://s3.test/response_audio/hua.m4a', 'https://s3.test/response_audio/mei.m4a'])
        ->and($urlsFor($hua))->toBe(['https://s3.test/response_audio/ming.m4a', 'https://s3.test/response_audio/mei.m4a'])
        ->and($urlsFor($mei))->toBe(['https://s3.test/response_audio/ming.m4a', 'https://s3.test/response_audio/hua.m4a']);
});

it('gives an empty recordings list when nobody else recorded, including when only the viewer did', function () {
    [$me, $other] = User::factory()->count(2)->create(['role' => 'viewer'])->all();
    $fish = Fish::factory()->create();
    recordingStorage();
    recordingRow($me, $fish, 'yayo', ['name' => 'dalakan', 'recording_filename' => 'mine.m4a', 'recording_seconds' => 9, 'recording_submitted_at' => '2026-11-21 10:00:00.000000']);
    recordingRow($other, $fish, 'yayo', ['name' => 'dalakan']);
    recordingRow($other, $fish, 'imowrod', ['name' => 'rakan']);

    $responses = responsesFor($me, $fish);

    expect(tribeSummary($responses, 'yayo')['names'])->toBe([['name' => 'dalakan', 'count' => 2, 'recordings' => []]])
        ->and(tribeSummary($responses, 'imowrod')['names'])->toBe([['name' => 'rakan', 'count' => 1, 'recordings' => []]])
        ->and($responses['mine'][0]['recording_url'])->toBe('https://s3.test/response_audio/mine.m4a');
});

it('orders recordings recorded at the same time by response id', function () {
    [$a, $b, $viewer] = User::factory()->count(3)->create(['role' => 'viewer'])->all();
    $fish = Fish::factory()->create();
    recordingStorage();
    $same = '2026-11-21 10:00:00.500000';
    recordingRow($a, $fish, 'yayo', ['name' => 'dalakan', 'recording_filename' => 'first.m4a', 'recording_seconds' => 5, 'recording_submitted_at' => $same]);
    recordingRow($b, $fish, 'yayo', ['name' => 'dalakan', 'recording_filename' => 'second.m4a', 'recording_seconds' => 5, 'recording_submitted_at' => $same]);

    $urls = array_column(tribeSummary(responsesFor($viewer, $fish), 'yayo')['names'][0]['recordings'], 'recording_url');

    expect($urls)->toBe(['https://s3.test/response_audio/first.m4a', 'https://s3.test/response_audio/second.m4a']);
});

it('puts spellings that differ only in case or surrounding spaces into one group and keeps old recordings without seconds', function () {
    [$a, $b, $c, $viewer] = User::factory()->count(4)->create(['role' => 'viewer'])->all();
    $fish = Fish::factory()->create();
    recordingStorage();
    recordingRow($a, $fish, 'yayo', ['name' => 'Dalakan', 'name_submitted_at' => '2026-11-21 09:00:00.000001', 'recording_filename' => 'a.m4a', 'recording_seconds' => 4, 'recording_submitted_at' => '2026-11-21 10:00:00.000001']);
    recordingRow($b, $fish, 'yayo', ['name' => 'dalakan', 'name_submitted_at' => '2026-11-21 09:00:00.000002', 'recording_filename' => 'b.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.000002']);
    recordingRow($c, $fish, 'yayo', ['name' => ' DALAKAN ', 'name_submitted_at' => '2026-11-21 09:00:00.000003', 'recording_filename' => 'c.m4a', 'recording_seconds' => 8, 'recording_submitted_at' => '2026-11-21 10:00:00.000003']);

    $names = tribeSummary(responsesFor($viewer, $fish), 'yayo')['names'];

    expect($names)->toHaveCount(1)
        ->and($names[0]['name'])->toBe('Dalakan')
        ->and($names[0]['count'])->toBe(3)
        ->and(array_column($names[0]['recordings'], 'recording_seconds'))->toBe([4, null, 8]);
});

it('does not count recordings of a deleted user or of a soft-deleted fish', function () {
    [$gone, $stays, $viewer] = User::factory()->count(3)->create(['role' => 'viewer'])->all();
    $fish = Fish::factory()->create();
    recordingStorage();
    recordingRow($gone, $fish, 'yayo', ['name' => 'dalakan', 'recording_filename' => 'gone.m4a', 'recording_seconds' => 3, 'recording_submitted_at' => '2026-11-21 10:00:00.000001']);
    recordingRow($stays, $fish, 'yayo', ['name' => 'dalakan', 'recording_filename' => 'stays.m4a', 'recording_seconds' => 5, 'recording_submitted_at' => '2026-11-21 10:00:00.000002']);
    $gone->delete();

    $urls = array_column(tribeSummary(responsesFor($viewer, $fish), 'yayo')['names'][0]['recordings'], 'recording_url');

    expect($urls)->toBe(['https://s3.test/response_audio/stays.m4a']);

    $fish->delete();
    test()->actingAs($viewer)->get("/fish/{$fish->id}")->assertNotFound();
});
