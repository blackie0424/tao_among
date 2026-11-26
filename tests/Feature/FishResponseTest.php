<?php

use App\Models\Fish;
use App\Models\FishAudio;
use App\Models\FishTribeResponse;
use App\Models\TribalClassification;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

function postResponse(User $user, Fish $fish, array $data)
{
    return test()->actingAs($user)->post("/fish/{$fish->id}/responses", $data);
}

function responsesProps(User $user, Fish $fish): array
{
    $props = [];
    test()->actingAs($user)->get("/fish/{$fish->id}")
        ->assertOk()
        ->assertInertia(function ($page) use (&$props) {
            $props = $page->toArray()['props']['responses'] ?? [];
        });

    return $props;
}

function summaryFor(array $responses, string $tribe): array
{
    return collect($responses['summary'])->firstWhere('tribe', $tribe);
}

function addResponse(User $user, Fish $fish, string $tribe, ?string $name, ?string $category = null): FishTribeResponse
{
    return FishTribeResponse::create([
        'user_id' => $user->id,
        'fish_id' => $fish->id,
        'tribe' => $tribe,
        'name' => $name,
        'food_category' => $category,
        'name_submitted_at' => $name === null ? null : FishTribeResponse::submissionTime(),
    ]);
}

function at(string $time): void
{
    Carbon::setTestNow(Carbon::parse($time));
}

// ─── AC1 回答資料與驗證 ───────────────────────────────────

it('stores a response for the logged-in user and ignores a user_id in the request', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);
    $other = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();

    postResponse($viewer, $fish, [
        'tribe' => 'imowrod', 'name' => 'dalakan', 'food_category' => 'oyod', 'user_id' => $other->id,
    ])->assertSessionHasNoErrors();

    $row = FishTribeResponse::firstOrFail();
    expect($row->user_id)->toBe($viewer->id)
        ->and($row->fish_id)->toBe($fish->id)
        ->and($row->tribe)->toBe('imowrod')
        ->and($row->name)->toBe('dalakan')
        ->and($row->food_category)->toBe('oyod');
});

it('rejects a response where both name and food category are empty', function (array $data) {
    $viewer = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();

    postResponse($viewer, $fish, ['tribe' => 'yayo', ...$data])->assertSessionHasErrors('name');

    expect(FishTribeResponse::count())->toBe(0);
})->with([
    'missing fields' => [[]],
    'null values' => [['name' => null, 'food_category' => null]],
    'empty string name' => [['name' => '', 'food_category' => '']],
    'whitespace-only name' => [['name' => "  \u{3000} ", 'food_category' => null]],
]);

it('treats whitespace-only name as unfilled but keeps the category', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();

    postResponse($viewer, $fish, ['tribe' => 'yayo', 'name' => '   ', 'food_category' => 'rahet'])
        ->assertSessionHasNoErrors();

    expect(FishTribeResponse::firstOrFail()->name)->toBeNull();
});

it('accepts "0" as a valid name and trims surrounding whitespace', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();

    postResponse($viewer, $fish, ['tribe' => 'yayo', 'name' => ' 0 ', 'food_category' => null])
        ->assertSessionHasNoErrors();

    expect(FishTribeResponse::firstOrFail()->name)->toBe('0');
});

it('accepts a 60-character name and rejects 61 characters', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();

    postResponse($viewer, $fish, ['tribe' => 'yayo', 'name' => str_repeat('魚', 60)])
        ->assertSessionHasNoErrors();
    expect(FishTribeResponse::count())->toBe(1);

    postResponse($viewer, $fish, ['tribe' => 'iratay', 'name' => str_repeat('魚', 61)])
        ->assertSessionHasErrors(['name' => '叫法最多 60 字']);
    expect(FishTribeResponse::count())->toBe(1);
});

it('counts Unicode characters after trimming, including characters outside the BMP', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    $astral = "\u{20BB7}"; // 𠮷，一個 Unicode 字元，UTF-16 要 2 個單位

    postResponse($viewer, $fish, ['tribe' => 'yayo', 'name' => str_repeat($astral, 60)])->assertSessionHasNoErrors();
    postResponse($viewer, $fish, ['tribe' => 'iratay', 'name' => '  '.str_repeat('魚', 60)."\u{3000} "])->assertSessionHasNoErrors();
    postResponse($viewer, $fish, ['tribe' => 'iranmeilek', 'name' => str_repeat($astral, 61)])
        ->assertSessionHasErrors(['name' => '叫法最多 60 字']);

    expect(FishTribeResponse::count())->toBe(2)
        ->and(FishTribeResponse::where('tribe', 'iratay')->value('name'))->toBe(str_repeat('魚', 60));
});

it('accepts every response food category, including kakanen no rarakeh, and counts it', function () {
    $fish = Fish::factory()->create();
    $users = User::factory()->count(5)->create(['role' => 'viewer']);
    foreach (['oyod', 'rahet', '不分類', '不食用', 'kakanen no rarakeh'] as $i => $category) {
        postResponse($users[$i], $fish, ['tribe' => 'yayo', 'food_category' => $category])->assertSessionHasNoErrors();
    }

    $categories = collect(summaryFor(responsesProps($users[0], $fish), 'yayo')['categories'])->pluck('count', 'value');

    expect(FishTribeResponse::count())->toBe(5)
        ->and($categories['kakanen no rarakeh'])->toBe(1);
});

it('rejects an unknown tribe and an unsupported food category', function (array $data, string $field) {
    $viewer = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();

    postResponse($viewer, $fish, ['name' => 'x', ...$data])->assertSessionHasErrors($field);

    expect(FishTribeResponse::count())->toBe(0);
})->with([
    'unknown tribe' => [['tribe' => 'unknown'], 'tribe'],
    'missing tribe' => [[], 'tribe'],
    'placeholder category' => [['tribe' => 'yayo', 'food_category' => '尚未紀錄'], 'food_category'],
    'question mark category' => [['tribe' => 'yayo', 'food_category' => '?'], 'food_category'],
    'free text category' => [['tribe' => 'yayo', 'food_category' => 'other'], 'food_category'],
]);

// ─── AC2 送出與更新 ──────────────────────────────────────

it('lets activated roles submit and blocks guests and anonymous visitors', function () {
    $fish = Fish::factory()->create();
    $data = ['tribe' => 'yayo', 'name' => 'x'];

    foreach (['viewer', 'editor', 'admin'] as $role) {
        postResponse(User::factory()->create(['role' => $role]), $fish, $data)->assertSessionHasNoErrors();
    }
    expect(FishTribeResponse::count())->toBe(3);

    postResponse(User::factory()->create(['role' => 'guest']), $fish, $data)->assertForbidden();
    auth()->logout();
    test()->post("/fish/{$fish->id}/responses", $data)->assertRedirect();

    expect(FishTribeResponse::count())->toBe(3);
});

it('replaces the previous answer for the same user, fish and tribe', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();

    postResponse($viewer, $fish, ['tribe' => 'yayo', 'name' => 'old', 'food_category' => 'oyod']);
    postResponse($viewer, $fish, ['tribe' => 'yayo', 'name' => 'new']);

    $row = FishTribeResponse::sole();
    expect($row->name)->toBe('new')
        ->and($row->food_category)->toBeNull();
});

it('adds a separate row for a different tribe', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();

    postResponse($viewer, $fish, ['tribe' => 'yayo', 'name' => 'a']);
    postResponse($viewer, $fish, ['tribe' => 'iratay', 'name' => 'b']);

    expect(FishTribeResponse::count())->toBe(2);
});

it('still requires one filled field when replacing an answer', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    postResponse($viewer, $fish, ['tribe' => 'yayo', 'name' => 'keep']);

    postResponse($viewer, $fish, ['tribe' => 'yayo'])->assertSessionHasErrors('name');

    expect(FishTribeResponse::sole()->name)->toBe('keep');
});

it('enforces one row per user, fish and tribe at the database level', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    addResponse($viewer, $fish, 'yayo', 'a');

    expect(fn () => addResponse($viewer, $fish, 'yayo', 'b'))->toThrow(QueryException::class);
});

it('does not touch official fish data', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create(['name' => '原名']);
    TribalClassification::factory()->create(['fish_id' => $fish->id, 'tribe' => 'yayo', 'food_category' => 'oyod']);
    FishAudio::factory()->create(['fish_id' => $fish->id]);

    postResponse($viewer, $fish, ['tribe' => 'yayo', 'name' => '另一個說法', 'food_category' => 'rahet']);

    expect($fish->fresh()->name)->toBe('原名')
        ->and(TribalClassification::count())->toBe(1)
        ->and(TribalClassification::first()->food_category)->toBe('oyod')
        ->and(FishAudio::count())->toBe(1);
});

it('returns 404 when the fish is soft-deleted', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    $fish->delete();

    postResponse($viewer, $fish, ['tribe' => 'yayo', 'name' => 'x'])->assertNotFound();
});

// ─── AC3 觀看與回應白名單 ────────────────────────────────

it('lets activated roles read the summary without answering first', function (string $role) {
    $fish = Fish::factory()->create();
    addResponse(User::factory()->create(['role' => 'viewer']), $fish, 'imowrod', '甲說法');

    $responses = responsesProps(User::factory()->create(['role' => $role]), $fish);

    expect($responses['mine'])->toBe([])
        ->and(summaryFor($responses, 'imowrod')['names'][0]['name'])->toBe('甲說法');
})->with(['viewer', 'editor', 'admin']);

it('gives unauthorised callers no response data and writes nothing, on every request type', function (string $who) {
    $fish = Fish::factory()->create();
    $answerer = User::factory()->create(['role' => 'viewer', 'email' => 'answerer-secret@example.test']);
    addResponse($answerer, $fish, 'imowrod', 'ZZ甲說法', 'oyod');
    $caller = $who === 'guest' ? User::factory()->create(['role' => 'guest']) : null;
    $caller ? test()->actingAs($caller) : auth()->logout();

    $requests = [
        'GET page' => fn () => test()->get("/fish/{$fish->id}"),
        'GET page as JSON' => fn () => test()->getJson("/fish/{$fish->id}"),
        'POST valid' => fn () => test()->post("/fish/{$fish->id}/responses", ['tribe' => 'yayo', 'name' => 'ZZ寫入嘗試']),
        'POST invalid' => fn () => test()->post("/fish/{$fish->id}/responses", ['tribe' => 'nope']),
        'GET again (reload)' => fn () => test()->get("/fish/{$fish->id}"),
    ];

    foreach ($requests as $label => $send) {
        $response = $send();
        expect($response->status())->toBeIn([302, 401, 403], $label);
        $body = $response->getContent();
        foreach (['ZZ甲說法', 'oyod', 'answerer-secret', 'summary', 'responders', 'has_variants', 'categories'] as $leak) {
            expect($body)->not->toContain($leak, "{$label} leaked {$leak}");
        }
    }

    expect(FishTribeResponse::count())->toBe(1);
})->with(['guest', 'anonymous']);

it('exposes exactly the whitelisted response fields to an activated caller and no one else\'s identity', function () {
    $fish = Fish::factory()->create();
    $me = User::factory()->create(['role' => 'viewer']);
    $other = User::factory()->create(['role' => 'viewer', 'email' => 'other-person@example.test', 'name' => 'OtherPersonName']);
    addResponse($other, $fish, 'yayo', '別人的說法', 'rahet');

    $responses = responsesProps($me, $fish);

    expect(array_keys($responses))->toBe(['form', 'summary', 'mine', 'responders'])
        ->and(array_keys($responses['summary'][0]))->toBe(['tribe', 'has_responses', 'names', 'recordings_only', 'has_variants', 'categories'])
        ->and(array_keys(summaryFor($responses, 'yayo')['names'][0]))->toBe(['name', 'count', 'recordings'])
        ->and($responses['responders'])->toBeInt();

    $json = json_encode($responses, JSON_UNESCAPED_UNICODE);
    expect($json)->not->toContain('other-person@example.test')
        ->and($json)->not->toContain('OtherPersonName')
        ->and($json)->not->toContain('"user_id"');
});

it('keeps the whitelist after a rejected submission and after a reload', function () {
    $fish = Fish::factory()->create();
    $me = User::factory()->create(['role' => 'viewer']);
    addResponse(User::factory()->create(['role' => 'viewer']), $fish, 'yayo', '別人的說法', 'rahet');

    postResponse($me, $fish, ['tribe' => 'yayo'])->assertSessionHasErrors('name');
    $afterError = responsesProps($me, $fish);
    $reloaded = responsesProps($me, $fish);

    expect(array_keys($afterError))->toBe(['form', 'summary', 'mine', 'responders'])
        ->and($afterError['mine'])->toBe([])
        ->and($reloaded)->toBe($afterError);
});

it('returns only the caller\'s own answers in mine', function () {
    $fish = Fish::factory()->create();
    $me = User::factory()->create(['role' => 'viewer']);
    addResponse($me, $fish, 'yayo', '我的說法', 'rahet');
    addResponse(User::factory()->create(['role' => 'viewer']), $fish, 'iratay', '別人的說法');

    $responses = responsesProps($me, $fish);

    expect($responses['mine'])->toHaveCount(1)
        ->and($responses['mine'][0])->toMatchArray(['tribe' => 'yayo', 'name' => '我的說法', 'food_category' => 'rahet']);
});

// ─── AC4 各部落說法卡的顯示 ──────────────────────────────

it('groups names by trimmed, case-insensitive spelling and shows the earliest original spelling', function () {
    $fish = Fish::factory()->create();
    [$u1, $u2, $u3] = User::factory()->count(3)->create(['role' => 'viewer']);
    addResponse($u1, $fish, 'imowrod', 'Dalakan', 'oyod');
    addResponse($u2, $fish, 'imowrod', ' dalakan ', 'oyod');
    addResponse($u3, $fish, 'imowrod', '乙');

    $imowrod = summaryFor(responsesProps($u1, $fish), 'imowrod');

    expect($imowrod['has_responses'])->toBeTrue()
        ->and($imowrod['names'])->toBe([
            ['name' => 'Dalakan', 'count' => 2, 'recordings' => []],
            ['name' => '乙', 'count' => 1, 'recordings' => []],
        ])
        ->and($imowrod['has_variants'])->toBeTrue();
});

it('orders names with equal counts by earliest submission', function () {
    $fish = Fish::factory()->create();
    [$u1, $u2] = User::factory()->count(2)->create(['role' => 'viewer']);
    addResponse($u1, $fish, 'iratay', 'B');
    addResponse($u2, $fish, 'iratay', 'A');

    $iratay = summaryFor(responsesProps($u1, $fish), 'iratay');

    expect(array_column($iratay['names'], 'name'))->toBe(['B', 'A']);
});

it('marks only tribes with more than one spelling as having variants', function () {
    $fish = Fish::factory()->create();
    [$u1, $u2] = User::factory()->count(2)->create(['role' => 'viewer']);
    addResponse($u1, $fish, 'yayo', 'same');
    addResponse($u2, $fish, 'yayo', 'SAME');

    expect(summaryFor(responsesProps($u1, $fish), 'yayo')['has_variants'])->toBeFalse();
});

it('distinguishes a tribe with no answers from a tribe whose names are all empty', function () {
    $fish = Fish::factory()->create();
    $user = User::factory()->create(['role' => 'viewer']);
    addResponse($user, $fish, 'iraraley', null, '不分類');

    $responses = responsesProps($user, $fish);

    expect(summaryFor($responses, 'ivalino')['has_responses'])->toBeFalse()
        ->and(summaryFor($responses, 'iraraley')['has_responses'])->toBeTrue()
        ->and(summaryFor($responses, 'iraraley')['names'])->toBe([]);
});

it('counts each food category separately and keeps "不分類" apart from unfilled', function () {
    $fish = Fish::factory()->create();
    [$u1, $u2, $u3] = User::factory()->count(3)->create(['role' => 'viewer']);
    addResponse($u1, $fish, 'yayo', 'a', 'oyod');
    addResponse($u2, $fish, 'yayo', 'b', '不分類');
    addResponse($u3, $fish, 'yayo', 'c', null);

    $categories = collect(summaryFor(responsesProps($u1, $fish), 'yayo')['categories'])->pluck('count', 'value');

    expect($categories->all())->toBe(['oyod' => 1, 'rahet' => 0, '不分類' => 1, '不食用' => 0, 'kakanen no rarakeh' => 0]);
});

it('lists tribes in the configured order', function () {
    $fish = Fish::factory()->create();
    $user = User::factory()->create(['role' => 'viewer']);

    $tribes = array_column(responsesProps($user, $fish)['summary'], 'tribe');

    expect($tribes)->toBe(config('fish_options.tribes'));
});

it('folds only English letters, not other scripts', function () {
    $fish = Fish::factory()->create();
    [$u1, $u2, $u3, $u4] = User::factory()->count(4)->create(['role' => 'viewer']);
    addResponse($u1, $fish, 'yayo', 'Abc');
    addResponse($u2, $fish, 'yayo', 'aBC');
    addResponse($u3, $fish, 'yayo', "\u{0391}\u{03B1}"); // 希臘文 Αα
    addResponse($u4, $fish, 'yayo', "\u{03B1}\u{03B1}"); // 希臘文 αα

    $yayo = summaryFor(responsesProps($u1, $fish), 'yayo');

    expect($yayo['names'])->toBe([
        ['name' => 'Abc', 'count' => 2, 'recordings' => []],
        ['name' => "\u{0391}\u{03B1}", 'count' => 1, 'recordings' => []],
        ['name' => "\u{03B1}\u{03B1}", 'count' => 1, 'recordings' => []],
    ])->and($yayo['has_variants'])->toBeTrue();
});

it('orders by when the current name was submitted, so changing a name moves it to the back', function () {
    $fish = Fish::factory()->create();
    [$u1, $u2] = User::factory()->count(2)->create(['role' => 'viewer']);

    Carbon::setTestNow('2026-11-19 10:00:00');
    postResponse($u1, $fish, ['tribe' => 'yayo', 'name' => 'B']);
    Carbon::setTestNow('2026-11-19 10:01:00');
    postResponse($u2, $fish, ['tribe' => 'yayo', 'name' => 'C']);
    Carbon::setTestNow('2026-11-19 10:02:00');
    postResponse($u1, $fish, ['tribe' => 'yayo', 'name' => 'D']);

    expect(array_column(summaryFor(responsesProps($u1, $fish), 'yayo')['names'], 'name'))->toBe(['C', 'D']);
});

it('shows the earliest current spelling of a group after someone changes their name into it', function () {
    $fish = Fish::factory()->create();
    [$u1, $u2] = User::factory()->count(2)->create(['role' => 'viewer']);

    Carbon::setTestNow('2026-11-19 10:00:00');
    postResponse($u1, $fish, ['tribe' => 'yayo', 'name' => 'X']);
    Carbon::setTestNow('2026-11-19 10:01:00');
    postResponse($u2, $fish, ['tribe' => 'yayo', 'name' => 'Dalakan']);
    Carbon::setTestNow('2026-11-19 10:02:00');
    postResponse($u1, $fish, ['tribe' => 'yayo', 'name' => 'DALAKAN']);

    expect(summaryFor(responsesProps($u1, $fish), 'yayo')['names'])->toBe([['name' => 'Dalakan', 'count' => 2, 'recordings' => []]]);
});

it('keeps the order when the same spelling is submitted again with other changes', function () {
    $fish = Fish::factory()->create();
    [$u1, $u2] = User::factory()->count(2)->create(['role' => 'viewer']);

    Carbon::setTestNow('2026-11-19 10:00:00');
    postResponse($u1, $fish, ['tribe' => 'yayo', 'name' => 'B']);
    Carbon::setTestNow('2026-11-19 10:01:00');
    postResponse($u2, $fish, ['tribe' => 'yayo', 'name' => 'C']);
    Carbon::setTestNow('2026-11-19 10:05:00');
    postResponse($u1, $fish, ['tribe' => 'yayo', 'name' => 'b', 'food_category' => 'oyod']);

    // 同一種寫法重送：不重排，也保留最早送出的原始寫法 B
    expect(array_column(summaryFor(responsesProps($u1, $fish), 'yayo')['names'], 'name'))->toBe(['B', 'C']);
});

it('tells apart submissions made within the same second', function () {
    $fish = Fish::factory()->create();
    [$u1, $u2] = User::factory()->count(2)->create(['role' => 'viewer']);

    at('2026-11-19 10:00:00.100000');
    postResponse($u1, $fish, ['tribe' => 'yayo', 'name' => 'B']);
    at('2026-11-19 10:00:00.200000');
    postResponse($u2, $fish, ['tribe' => 'yayo', 'name' => 'C']);
    at('2026-11-19 10:00:00.300000');
    postResponse($u1, $fish, ['tribe' => 'yayo', 'name' => 'D']);

    expect(array_column(summaryFor(responsesProps($u1, $fish), 'yayo')['names'], 'name'))->toBe(['C', 'D']);
});

it('shows the earlier spelling when a same-second change joins an existing group', function () {
    $fish = Fish::factory()->create();
    [$u1, $u2] = User::factory()->count(2)->create(['role' => 'viewer']);

    at('2026-11-19 10:00:00.100000');
    postResponse($u1, $fish, ['tribe' => 'yayo', 'name' => 'X']);
    at('2026-11-19 10:00:00.200000');
    postResponse($u2, $fish, ['tribe' => 'yayo', 'name' => 'Dalakan']);
    at('2026-11-19 10:00:00.300000');
    postResponse($u1, $fish, ['tribe' => 'yayo', 'name' => 'DALAKAN']);

    expect(summaryFor(responsesProps($u1, $fish), 'yayo')['names'])->toBe([['name' => 'Dalakan', 'count' => 2, 'recordings' => []]]);
});

it('keeps the original spelling when someone later changes only the letter case', function () {
    $fish = Fish::factory()->create();
    [$u1, $u2] = User::factory()->count(2)->create(['role' => 'viewer']);

    at('2026-11-19 10:00:00.100000');
    postResponse($u1, $fish, ['tribe' => 'yayo', 'name' => 'Dalakan']);
    at('2026-11-19 10:00:00.200000');
    postResponse($u2, $fish, ['tribe' => 'yayo', 'name' => 'dalakan']);
    at('2026-11-19 10:00:00.300000');
    postResponse($u1, $fish, ['tribe' => 'yayo', 'name' => 'DALAKAN']);

    expect(summaryFor(responsesProps($u1, $fish), 'yayo')['names'])->toBe([['name' => 'Dalakan', 'count' => 2, 'recordings' => []]])
        ->and(responsesProps($u1, $fish)['mine'][0]['name'])->toBe('Dalakan');
});

it('restamps the time when a name is changed away and back again', function () {
    $fish = Fish::factory()->create();
    $u1 = User::factory()->create(['role' => 'viewer']);

    at('2026-11-19 10:00:00.100000');
    postResponse($u1, $fish, ['tribe' => 'yayo', 'name' => 'X']);
    at('2026-11-19 10:00:00.200000');
    postResponse($u1, $fish, ['tribe' => 'yayo', 'name' => 'Y']);
    at('2026-11-19 10:00:00.300000');
    postResponse($u1, $fish, ['tribe' => 'yayo', 'name' => 'X']);

    $row = FishTribeResponse::sole();
    expect($row->name)->toBe('X')
        ->and($row->name_submitted_at)->toBe('2026-11-19 10:00:00.300000');
});

it('applies the later request on top of a row another request already created', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();
    DB::table('fish_tribe_responses')->insert([
        'user_id' => $viewer->id, 'fish_id' => $fish->id, 'tribe' => 'yayo', 'name' => '搶先',
        'food_category' => null, 'name_submitted_at' => '2026-11-19 10:00:00.000000',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    postResponse($viewer, $fish, ['tribe' => 'yayo', 'name' => '後到', 'food_category' => 'oyod'])->assertSessionHasNoErrors();

    $row = FishTribeResponse::sole();
    expect($row->name)->toBe('後到')->and($row->food_category)->toBe('oyod');
});

// ─── AC5 這條魚的回應人數 ────────────────────────────────

it('counts the distinct users who answered this fish, not rows and not other fish', function () {
    $fish = Fish::factory()->create();
    $otherFish = Fish::factory()->create();
    [$u1, $u2, $u3] = User::factory()->count(3)->create(['role' => 'viewer']);
    addResponse($u1, $fish, 'yayo', 'x');
    addResponse($u1, $fish, 'iratay', 'y'); // 同一人答第二個部落，仍算 1 人
    addResponse($u2, $fish, 'yayo', 'z');
    addResponse($u3, $otherFish, 'yayo', 'w'); // 別條魚不算

    expect(responsesProps($u3, $fish)['responders'])->toBe(2)
        ->and(responsesProps($u3, $otherFish)['responders'])->toBe(1);
});

it('shows zero responders when nobody has answered this fish', function () {
    $fish = Fish::factory()->create();
    $user = User::factory()->create(['role' => 'viewer']);

    expect(responsesProps($user, $fish)['responders'])->toBe(0);
});

it('removes a user\'s answers, and the count, when the user is deleted', function () {
    $fish = Fish::factory()->create();
    $keeper = User::factory()->create(['role' => 'viewer']);
    $leaver = User::factory()->create(['role' => 'viewer']);
    addResponse($keeper, $fish, 'yayo', 'x');
    addResponse($leaver, $fish, 'iratay', 'y');
    expect(responsesProps($keeper, $fish)['responders'])->toBe(2);

    $leaver->delete();

    expect(FishTribeResponse::count())->toBe(1)
        ->and(responsesProps($keeper, $fish)['responders'])->toBe(1);
});

it('stops counting answers of a soft-deleted fish and counts them again after restore', function () {
    $fish = Fish::factory()->create();
    $user = User::factory()->create(['role' => 'viewer']);
    addResponse($user, $fish, 'yayo', 'x', 'oyod');

    $fish->delete();
    test()->actingAs($user)->get("/fish/{$fish->id}")->assertNotFound();
    postResponse($user, $fish, ['tribe' => 'iratay', 'name' => 'y'])->assertNotFound();

    $fish->restore();
    $responses = responsesProps($user, $fish);
    expect($responses['responders'])->toBe(1)
        ->and(summaryFor($responses, 'yayo')['names'])->toBe([['name' => 'x', 'count' => 1, 'recordings' => []]]);
});

it('keeps a name containing HTML exactly as typed (it is shown as plain text by the page)', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create();

    postResponse($viewer, $fish, ['tribe' => 'yayo', 'name' => '<script>alert(1)</script>'])->assertSessionHasNoErrors();

    expect(FishTribeResponse::sole()->name)->toBe('<script>alert(1)</script>')
        ->and(summaryFor(responsesProps($viewer, $fish), 'yayo')['names'][0]['name'])->toBe('<script>alert(1)</script>');
});

// ─── AC6 表單選項 ────────────────────────────────────────

it('builds form options from config and the fish\'s current name', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create(['name' => '目前名稱']);

    $form = responsesProps($user, $fish)['form'];

    expect($form['tribes'])->toBe(config('fish_options.tribes'))
        ->and($form['food_categories'])->toBe(['oyod', 'rahet', '不分類', '不食用', 'kakanen no rarakeh'])
        ->and($form['name_options'])->toBe(['目前名稱'])
        ->and($form['name_max_length'])->toBe(60);
});

it('omits the placeholder name from the name options', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create(['name' => '我不知道']);

    expect(responsesProps($user, $fish)['form']['name_options'])->toBe([]);
});

// #172：魚島作答寫進同一張表，among 魚頁的彙總輸出格式與人數規則不變
it('keeps the among summary unchanged when island answers add processing method and respondent tribe', function () {
    $fish = Fish::factory()->create();
    $a = User::factory()->create(['role' => 'viewer']);
    $b = User::factory()->create(['role' => 'viewer']);
    FishTribeResponse::create(['user_id' => $a->id, 'fish_id' => $fish->id, 'tribe' => 'yayo', 'name' => 'arayo', 'food_category' => 'oyod', 'processing_method' => '剝皮', 'respondent_tribe' => 'iratay']);
    FishTribeResponse::create(['user_id' => $b->id, 'fish_id' => $fish->id, 'tribe' => 'yayo', 'processing_method' => '去魚鱗', 'respondent_tribe' => 'yayo']);

    $yayo = summaryFor(['summary' => FishTribeResponse::summaryForFish($fish->id)], 'yayo');

    expect(array_keys($yayo))->toBe(['tribe', 'has_responses', 'names', 'recordings_only', 'has_variants', 'categories'])
        ->and($yayo['names'])->toBe([['name' => 'arayo', 'count' => 1, 'recordings' => []]])
        ->and(collect($yayo['categories'])->firstWhere('value', 'oyod')['count'])->toBe(1);
});
