<?php

use App\Models\Fish;
use App\Models\FishTribeResponse;
use App\Models\IslandCharacter;
use App\Models\IslandCollectedFish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

// #232：初學者的圖鑑——海邊拿到的魚，自己部落記下至少一題就收進圖鑑

function collector(string $tribe = 'yayo', string $name = '阿海'): User
{
    $user = User::factory()->create();
    IslandCharacter::create(['user_id' => $user->id, 'name' => $name, 'tribe' => $tribe]);

    return $user;
}

function characterOf(User $user): IslandCharacter
{
    return IslandCharacter::where('user_id', $user->id)->firstOrFail();
}

function recordOf(User $user, Fish $fish, string $tribe, array $answer): FishTribeResponse
{
    return FishTribeResponse::create(['user_id' => $user->id, 'fish_id' => $fish->id, 'tribe' => $tribe, ...$answer]);
}

function collect232(User $user, int $fishId)
{
    return test()->actingAs($user)->post("/island/collection/{$fishId}");
}

function collectedIds(User $user): array
{
    return IslandCollectedFish::where('island_character_id', characterOf($user)->id)->pluck('fish_id')->all();
}

// AC 6：收進圖鑑

it('#232 自己部落有回答：收進圖鑑、回上一頁，收集時間是那一刻', function () {
    Carbon::setTestNow('2026-11-28 20:00:00');
    $player = collector('yayo');
    $fish = Fish::factory()->create();
    recordOf($player, $fish, 'yayo', ['food_category' => 'oyod']);

    collect232($player, $fish->id)->assertRedirect()->assertSessionHasNoErrors();

    $row = IslandCollectedFish::firstOrFail();
    expect($row->island_character_id)->toBe(characterOf($player)->id)
        ->and($row->fish_id)->toBe($fish->id)
        ->and($row->collected_at->toDateTimeString())->toBe('2026-11-28 20:00:00');
});

it('#232 「有回答」＝叫法、分類、處理方式至少一欄有值；處理方式「我不知道」也算', function (array $answer) {
    $player = collector('yayo');
    $fish = Fish::factory()->create();
    recordOf($player, $fish, 'yayo', $answer);

    collect232($player, $fish->id)->assertSessionHasNoErrors();

    expect(collectedIds($player))->toBe([$fish->id]);
})->with([
    '只有叫法' => [['name' => 'arayo']],
    '只有分類' => [['food_category' => 'rahet']],
    '只有處理方式「我不知道」' => [['processing_method' => '我不知道']],
]);

it('#232 只有錄音、三欄都空、或只有別的部落的回答：拒收，顯示「先記下至少一題」', function (?array $answer, string $tribe) {
    $player = collector('yayo');
    $fish = Fish::factory()->create();
    if ($answer !== null) {
        recordOf($player, $fish, $tribe, $answer);
    }

    collect232($player, $fish->id)->assertSessionHasErrors(['fish' => '先記下至少一題']);

    expect(collectedIds($player))->toBe([]);
})->with([
    '沒有回答' => [null, 'yayo'],
    '只有錄音' => [['recording_filename' => 'a.webm', 'recording_seconds' => 3], 'yayo'],
    '三欄被清空' => [['name' => '', 'food_category' => null, 'processing_method' => ''], 'yayo'],
    '只有別的部落' => [['food_category' => 'oyod'], 'iratay'],
]);

it('#232 同一條魚再收一次：不新增、不改收集時間', function () {
    $player = collector();
    $fish = Fish::factory()->create();
    recordOf($player, $fish, 'yayo', ['food_category' => 'oyod']);
    Carbon::setTestNow('2026-11-28 20:00:00');
    collect232($player, $fish->id);

    Carbon::setTestNow('2026-11-28 21:00:00');
    collect232($player, $fish->id)->assertSessionHasNoErrors();

    expect(IslandCollectedFish::count())->toBe(1)
        ->and(IslandCollectedFish::first()->collected_at->toDateTimeString())->toBe('2026-11-28 20:00:00');
});

it('#232 只收進自己的角色：別人的回答不能讓我收', function () {
    $player = collector('yayo', '甲');
    $other = collector('yayo', '乙');
    $fish = Fish::factory()->create();
    recordOf($other, $fish, 'yayo', ['food_category' => 'oyod']);

    collect232($player, $fish->id)->assertSessionHasErrors('fish');

    expect(IslandCollectedFish::count())->toBe(0);
});

it('#232 軟刪除或不存在的魚回 404', function () {
    $player = collector();
    $fish = Fish::factory()->create();
    recordOf($player, $fish, 'yayo', ['food_category' => 'oyod']);
    $fish->delete();

    collect232($player, $fish->id)->assertNotFound();
    collect232($player, 999999)->assertNotFound();

    expect(IslandCollectedFish::count())->toBe(0);
});

it('#232 沒有魚島角色：導到角色設定頁、不寫入', function () {
    $fish = Fish::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post("/island/collection/{$fish->id}")
        ->assertRedirect(route('island.character'));

    expect(IslandCollectedFish::count())->toBe(0);
});

it('#232 收進圖鑑和作答共用每分鐘 30 次的限流', function () {
    $player = collector();
    $fish = Fish::factory()->create();
    recordOf($player, $fish, 'yayo', ['food_category' => 'oyod']);
    for ($i = 0; $i < 30; $i++) {
        $this->actingAs($player)->post("/island/tribes/yayo/fish/{$fish->id}/answers", ['question' => 'food_category', 'value' => 'oyod']);
    }

    collect232($player, $fish->id)->assertStatus(429);

    expect(IslandCollectedFish::count())->toBe(0);
});

// AC 7：/island 帶圖鑑（依收集時間編號、自己部落的記錄）

function homeProps(User $user): array
{
    return test()->actingAs($user)->get('/island')->assertOk()->inertiaProps();
}

function collectAt(User $user, Fish $fish, string $at): void
{
    IslandCollectedFish::create(['island_character_id' => characterOf($user)->id, 'fish_id' => $fish->id, 'collected_at' => $at]);
}

it('#232 圖鑑依收集時間排、同時間依收集紀錄編號，第 N 條從 1 開始；帶自己部落的記錄，沒記的題目為空', function () {
    $player = collector('yayo');
    $a = Fish::factory()->create();
    $b = Fish::factory()->create();
    $c = Fish::factory()->create();
    recordOf($player, $a, 'yayo', ['name' => 'arayo', 'food_category' => 'oyod', 'processing_method' => '去魚鱗']);
    recordOf($player, $b, 'yayo', ['food_category' => 'rahet']);
    recordOf($player, $b, 'iratay', ['name' => '別部落的叫法', 'processing_method' => '剝皮']);
    collectAt($player, $b, '2026-11-28 10:00:00');
    collectAt($player, $c, '2026-11-28 11:00:00');
    collectAt($player, $a, '2026-11-28 10:00:00');

    expect(homeProps($player)['collection'])->toBe([
        ['fish_id' => $b->id, 'number' => 1, 'my' => ['name' => null, 'food_category' => 'rahet', 'processing_method' => null]],
        ['fish_id' => $a->id, 'number' => 2, 'my' => ['name' => 'arayo', 'food_category' => 'oyod', 'processing_method' => '去魚鱗']],
        ['fish_id' => $c->id, 'number' => 3, 'my' => ['name' => null, 'food_category' => null, 'processing_method' => null]],
    ]);
});

it('#232 圖鑑不列軟刪除的魚，後面的編號往前補；收集紀錄保留', function () {
    $player = collector();
    $a = Fish::factory()->create();
    $b = Fish::factory()->create();
    collectAt($player, $a, '2026-11-28 10:00:00');
    collectAt($player, $b, '2026-11-28 11:00:00');
    $a->delete();

    $collection = homeProps($player)['collection'];

    expect(array_column($collection, 'fish_id'))->toBe([$b->id])
        ->and($collection[0]['number'])->toBe(1)
        ->and(IslandCollectedFish::count())->toBe(2);
});

it('#232 圖鑑只有自己角色的；沒收集過是空的', function () {
    $player = collector('yayo', '甲');
    $other = collector('yayo', '乙');
    collectAt($other, Fish::factory()->create(), '2026-11-28 10:00:00');

    expect(homeProps($player)['collection'])->toBe([]);
});

// AC 9、11：家人說的話依部落、魚編號給

it('#232 fish_speech：每個部落每條魚的家人說的話與有沒有說法，和地點清單的那一筆相同', function () {
    $fish = Fish::factory()->create(['id' => 1, 'name' => '魚A']);
    FishTribeResponse::create(['user_id' => User::factory()->create()->id, 'fish_id' => 1, 'tribe' => 'iratay', 'food_category' => 'oyod', 'processing_method' => '去魚鱗']);
    Fish::factory()->create(['id' => 2])->delete();

    $speech = homeProps(collector())['fish_speech'];

    expect(array_keys($speech))->toEqualCanonicalizing(config('fish_options.tribes'))
        ->and(array_keys($speech['iratay']))->toBe([1])
        ->and($speech['iratay'][1])->toBe([
            'speech' => '這是 魚A。我們 iratay 多數人說它是 oyod；多數人說抓回來去魚鱗吃。',
            'has_detail' => true,
        ])
        ->and($speech['yayo'][1])->toBe([
            'speech' => '這是 魚A。我們 yayo 還沒有分類的答案；怎麼處理還沒有答案。',
            'has_detail' => false,
        ]);
});

// AC 5：記錄視窗的三題（延後載入）

it('#232 一般打開 /island 不帶記錄視窗的資料', function () {
    Fish::factory()->create();

    expect(homeProps(collector()))->not->toHaveKey('record_questions');
});

it('#232 partial reload 帶 record_fish：給自己部落這條魚三題的選項與我的回答，不給結果', function () {
    $player = collector('yayo');
    $fish = Fish::factory()->create(['name' => 'arayo']);
    recordOf($player, $fish, 'yayo', ['food_category' => 'oyod']);
    recordOf($player, $fish, 'iratay', ['food_category' => 'rahet']);
    FishTribeResponse::create(['user_id' => User::factory()->create()->id, 'fish_id' => $fish->id, 'tribe' => 'yayo', 'name' => 'alayo']);

    $this->actingAs($player)
        ->get("/island?record_fish={$fish->id}")
        ->assertInertia(fn ($page) => $page->reloadOnly('record_questions', fn ($reload) => $reload
            ->where('record_questions', [
                ['key' => 'name', 'options' => ['arayo', 'alayo'], 'my_answer' => null],
                ['key' => 'food_category', 'options' => FishTribeResponse::selectableFoodCategories(), 'my_answer' => 'oyod'],
                ['key' => 'processing_method', 'options' => config('fish_options.response_processing_methods'), 'my_answer' => null],
            ])));
});

it('#232 record_fish 是軟刪除、不存在或不是數字：記錄視窗的資料為空', function (string $value) {
    $player = collector();
    Fish::factory()->create(['id' => 5])->delete();

    $this->actingAs($player)
        ->get("/island?record_fish={$value}")
        ->assertInertia(fn ($page) => $page->reloadOnly('record_questions', fn ($reload) => $reload
            ->where('record_questions', null)));
})->with(['軟刪除' => '5', '不存在' => '999', '不是數字' => 'abc']);

// AC 4：手上的魚存在瀏覽器、依角色分開，要角色編號當鍵

it('#232 /island 帶角色編號（手上的魚依角色分開存）', function () {
    $player = collector();

    expect(homeProps($player)['character_id'])->toBe(characterOf($player)->id);
});
