<?php

use App\Models\Fish;
use App\Models\FishAudio;
use App\Models\FishTribeResponse;
use App\Models\TribalClassification;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
    ]);
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
    TribalClassification::factory()->create(['fish_id' => $fish->id, 'tribe' => 'yayo']);
    FishAudio::factory()->create(['fish_id' => $fish->id]);

    postResponse($viewer, $fish, ['tribe' => 'yayo', 'name' => '另一個說法', 'food_category' => 'rahet']);

    expect($fish->fresh()->name)->toBe('原名')
        ->and(TribalClassification::count())->toBe(1)
        ->and(TribalClassification::first()->food_category)->not->toBe('rahet')
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

it('exposes no response data to guests or anonymous visitors', function () {
    $fish = Fish::factory()->create();
    addResponse(User::factory()->create(['role' => 'viewer']), $fish, 'imowrod', '甲說法', 'oyod');

    $guest = User::factory()->create(['role' => 'guest']);
    test()->actingAs($guest)->get("/fish/{$fish->id}")
        ->assertForbidden()
        ->assertDontSee('甲說法');

    auth()->logout();
    test()->get("/fish/{$fish->id}")->assertRedirect()->assertDontSee('甲說法');
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
            ['name' => 'Dalakan', 'count' => 2],
            ['name' => '乙', 'count' => 1],
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

    expect($categories->all())->toBe(['oyod' => 1, 'rahet' => 0, '不分類' => 1, '不食用' => 0]);
});

it('lists tribes in the configured order', function () {
    $fish = Fish::factory()->create();
    $user = User::factory()->create(['role' => 'viewer']);

    $tribes = array_column(responsesProps($user, $fish)['summary'], 'tribe');

    expect($tribes)->toBe(config('fish_options.tribes'));
});

// ─── AC5 全站進度 ────────────────────────────────────────

it('counts fish with at least one answer over all live fish', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    [$a, $b, $c] = Fish::factory()->count(3)->create();
    addResponse($user, $a, 'yayo', 'x');
    addResponse(User::factory()->create(['role' => 'viewer']), $a, 'yayo', 'y');
    addResponse($user, $b, 'yayo', 'z');

    expect(responsesProps($user, $c)['progress'])->toBe(['answered' => 2, 'total' => 3]);
});

it('ignores soft-deleted fish in both numbers and counts them again after restore', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    [$a, $b, $c] = Fish::factory()->count(3)->create();
    addResponse($user, $a, 'yayo', 'x');
    addResponse($user, $b, 'yayo', 'z');

    $b->delete();
    expect(responsesProps($user, $c)['progress'])->toBe(['answered' => 1, 'total' => 2]);

    $b->restore();
    expect(responsesProps($user, $c)['progress'])->toBe(['answered' => 2, 'total' => 3]);
});

it('removes a user\'s answers when the user is deleted', function () {
    $keeper = User::factory()->create(['role' => 'viewer']);
    $leaver = User::factory()->create(['role' => 'viewer']);
    [$a, $b] = Fish::factory()->count(2)->create();
    addResponse($keeper, $a, 'yayo', 'x');
    addResponse($leaver, $b, 'yayo', 'y');

    $leaver->delete();

    expect(FishTribeResponse::count())->toBe(1)
        ->and(responsesProps($keeper, $a)['progress'])->toBe(['answered' => 1, 'total' => 2]);
});

it('reports zero over zero when there are no fish', function () {
    expect(FishTribeResponse::progress())->toBe(['answered' => 0, 'total' => 0]);
});

// ─── AC6 表單選項 ────────────────────────────────────────

it('builds form options from config and the fish\'s current name', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create(['name' => '目前名稱']);

    $form = responsesProps($user, $fish)['form'];

    expect($form['tribes'])->toBe(config('fish_options.tribes'))
        ->and($form['food_categories'])->toBe(['oyod', 'rahet', '不分類', '不食用'])
        ->and($form['name_options'])->toBe(['目前名稱'])
        ->and($form['name_max_length'])->toBe(60);
});

it('omits the placeholder name from the name options', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    $fish = Fish::factory()->create(['name' => '我不知道']);

    expect(responsesProps($user, $fish)['form']['name_options'])->toBe([]);
});
