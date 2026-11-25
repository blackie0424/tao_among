<?php

use App\Contracts\StorageServiceInterface;
use App\Models\Fish;
use App\Models\FishTribeResponse;
use App\Models\IslandCharacter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** 有魚島角色、也能用 among 魚頁（viewer）的玩家 */
function answeringPlayer(string $tribe = 'yayo', string $name = '阿海'): User
{
    $user = User::factory()->create(['role' => 'viewer']);
    IslandCharacter::create(['user_id' => $user->id, 'name' => $name, 'tribe' => $tribe]);

    return $user;
}

function islandSubmit(?User $user, Fish $fish, string $tribe, array $data)
{
    $test = $user ? test()->actingAs($user) : test();

    return $test->post("/island/tribes/{$tribe}/fish/{$fish->id}/answers", $data);
}

function islandRow(User $user, Fish $fish, string $tribe): ?FishTribeResponse
{
    return FishTribeResponse::where(['user_id' => $user->id, 'fish_id' => $fish->id, 'tribe' => $tribe])->first();
}

/** among 魚頁刪錄音用到的儲存服務：刪檔一律成功 */
function deletableRecordingStorage(): void
{
    test()->mock(StorageServiceInterface::class, function ($mock) {
        $mock->shouldIgnoreMissing();
        $mock->shouldReceive('delete')->andReturn(true);
    });
}

// AC 2：一次送出一題
it('魚島送出一題：新增一筆，只有這一題有值，記下回答者自己的部落', function () {
    $player = answeringPlayer('iratay');
    $fish = Fish::factory()->create();

    islandSubmit($player, $fish, 'yayo', ['question' => 'processing_method', 'value' => '去魚鱗'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $row = islandRow($player, $fish, 'yayo');
    expect($row->processing_method)->toBe('去魚鱗')
        ->and($row->name)->toBeNull()
        ->and($row->food_category)->toBeNull()
        ->and($row->respondent_tribe)->toBe('iratay');
});

it('魚島依序送出三題：同一筆，先答的題目維持原值', function () {
    $player = answeringPlayer();
    $fish = Fish::factory()->create();

    islandSubmit($player, $fish, 'yayo', ['question' => 'name', 'value' => 'dalakan'])->assertSessionHasNoErrors();
    islandSubmit($player, $fish, 'yayo', ['question' => 'food_category', 'value' => 'oyod'])->assertSessionHasNoErrors();
    islandSubmit($player, $fish, 'yayo', ['question' => 'processing_method', 'value' => '我不知道'])->assertSessionHasNoErrors();

    expect(FishTribeResponse::count())->toBe(1);
    $row = islandRow($player, $fish, 'yayo');
    expect($row->name)->toBe('dalakan')
        ->and($row->name_submitted_at)->not->toBeNull()
        ->and($row->food_category)->toBe('oyod')
        ->and($row->processing_method)->toBe('我不知道');
});

it('改答同一題：同一筆更新，不多一筆', function () {
    $player = answeringPlayer();
    $fish = Fish::factory()->create();

    islandSubmit($player, $fish, 'yayo', ['question' => 'food_category', 'value' => 'oyod']);
    islandSubmit($player, $fish, 'yayo', ['question' => 'food_category', 'value' => 'rahet']);

    expect(FishTribeResponse::count())->toBe(1)
        ->and(islandRow($player, $fish, 'yayo')->food_category)->toBe('rahet');
});

it('回答的部落取網址上的部落，表單帶的部落不採用', function () {
    $player = answeringPlayer('yayo');
    $fish = Fish::factory()->create();

    islandSubmit($player, $fish, 'imowrod', ['question' => 'food_category', 'value' => 'oyod', 'tribe' => 'yayo']);

    expect(islandRow($player, $fish, 'imowrod'))->not->toBeNull()
        ->and(islandRow($player, $fish, 'yayo'))->toBeNull();
});

it('叫法換成同一種寫法（只差大小寫或前後空白）時保留原寫法與送出時間', function () {
    $player = answeringPlayer();
    $fish = Fish::factory()->create();
    islandSubmit($player, $fish, 'yayo', ['question' => 'name', 'value' => 'Dalakan']);
    $firstTime = islandRow($player, $fish, 'yayo')->name_submitted_at;

    islandSubmit($player, $fish, 'yayo', ['question' => 'name', 'value' => '  dalakan　']);

    $row = islandRow($player, $fish, 'yayo');
    expect($row->name)->toBe('Dalakan')
        ->and($row->name_submitted_at)->toBe($firstTime);
});

it('叫法換成不同寫法時更新叫法與送出時間', function () {
    $player = answeringPlayer();
    $fish = Fish::factory()->create();
    FishTribeResponse::create([
        'user_id' => $player->id, 'fish_id' => $fish->id, 'tribe' => 'yayo',
        'name' => 'dalakan', 'name_submitted_at' => '2026-11-20 10:00:00.000001',
    ]);

    islandSubmit($player, $fish, 'yayo', ['question' => 'name', 'value' => 'arayo']);

    $row = islandRow($player, $fish, 'yayo');
    expect($row->name)->toBe('arayo')
        ->and($row->name_submitted_at)->not->toBe('2026-11-20 10:00:00.000001');
});

it('每次在魚島送出都把回答者自己的部落更新成目前角色的部落', function () {
    $player = answeringPlayer('iratay');
    $fish = Fish::factory()->create();
    FishTribeResponse::create(['user_id' => $player->id, 'fish_id' => $fish->id, 'tribe' => 'yayo', 'name' => 'dalakan']);

    islandSubmit($player, $fish, 'yayo', ['question' => 'food_category', 'value' => 'oyod']);

    $row = islandRow($player, $fish, 'yayo');
    expect($row->respondent_tribe)->toBe('iratay')
        ->and($row->name)->toBe('dalakan');
});

it('題目不是三題之一時拒絕，資料不變', function (array $data) {
    $player = answeringPlayer();
    $fish = Fish::factory()->create();

    islandSubmit($player, $fish, 'yayo', $data)->assertSessionHasErrors('question');

    expect(FishTribeResponse::count())->toBe(0);
})->with([
    '沒有題目' => [['value' => 'oyod']],
    '錄音' => [['question' => 'recording_filename', 'value' => 'a.m4a']],
    '部落' => [['question' => 'tribe', 'value' => 'yayo']],
]);

it('飲食分類或處理方式不在選項裡時拒絕，資料不變', function (string $question, string $value) {
    $player = answeringPlayer();
    $fish = Fish::factory()->create();
    islandSubmit($player, $fish, 'yayo', ['question' => 'food_category', 'value' => 'oyod']);

    islandSubmit($player, $fish, 'yayo', ['question' => $question, 'value' => $value])->assertSessionHasErrors('value');

    $row = islandRow($player, $fish, 'yayo');
    expect($row->food_category)->toBe('oyod')->and($row->processing_method)->toBeNull();
})->with([
    ['food_category', '我不知道'],
    ['food_category', '?'],
    ['food_category', ''],
    ['processing_method', '尚未紀錄'],
    ['processing_method', '?'],
    ['processing_method', ''],
]);

it('飲食分類與處理方式的每個選項都收得下', function () {
    $player = answeringPlayer();
    $fish = Fish::factory()->create();

    foreach (config('fish_options.response_food_categories') as $category) {
        islandSubmit($player, $fish, 'yayo', ['question' => 'food_category', 'value' => $category])->assertSessionHasNoErrors();
        expect(islandRow($player, $fish, 'yayo')->food_category)->toBe($category);
    }
    foreach (['去魚鱗', '不去魚鱗', '剝皮', '不食用', '我不知道'] as $method) {
        islandSubmit($player, $fish, 'yayo', ['question' => 'processing_method', 'value' => $method])->assertSessionHasNoErrors();
        expect(islandRow($player, $fish, 'yayo')->processing_method)->toBe($method);
    }
});

it('魚不存在、已軟刪除或部落不合法時回 404，不寫入', function () {
    $player = answeringPlayer();
    $deleted = Fish::factory()->create();
    $deleted->delete();
    $fish = Fish::factory()->create();

    test()->actingAs($player)->post('/island/tribes/yayo/fish/999999/answers', ['question' => 'food_category', 'value' => 'oyod'])->assertNotFound();
    islandSubmit($player, $deleted, 'yayo', ['question' => 'food_category', 'value' => 'oyod'])->assertNotFound();
    islandSubmit($player, $fish, 'nope', ['question' => 'food_category', 'value' => 'oyod'])->assertNotFound();

    expect(FishTribeResponse::count())->toBe(0);
});

it('沒有角色或沒登入送出時導到角色設定頁，不寫入', function () {
    $fish = Fish::factory()->create();

    islandSubmit(null, $fish, 'yayo', ['question' => 'food_category', 'value' => 'oyod'])->assertRedirect('/island/character');
    islandSubmit(User::factory()->create(), $fish, 'yayo', ['question' => 'food_category', 'value' => 'oyod'])->assertRedirect('/island/character');

    expect(FishTribeResponse::count())->toBe(0);
});

// AC 2：among 魚頁與魚島共用同一筆
it('among 魚頁送出時，處理方式、回答者自己的部落與錄音維持原值', function () {
    $player = answeringPlayer('iratay');
    $fish = Fish::factory()->create();
    islandSubmit($player, $fish, 'yayo', ['question' => 'processing_method', 'value' => '剝皮']);
    FishTribeResponse::where('fish_id', $fish->id)->update(['recording_filename' => 'a.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.000001', 'recording_seconds' => 5]);

    test()->actingAs($player)->post("/fish/{$fish->id}/responses", ['tribe' => 'yayo', 'name' => 'dalakan', 'food_category' => 'rahet'])
        ->assertSessionHasNoErrors();

    $row = islandRow($player, $fish, 'yayo');
    expect($row->name)->toBe('dalakan')
        ->and($row->food_category)->toBe('rahet')
        ->and($row->processing_method)->toBe('剝皮')
        ->and($row->respondent_tribe)->toBe('iratay')
        ->and($row->recording_filename)->toBe('a.m4a');
});

it('among 魚頁照舊覆寫叫法與分類兩欄：沒填的那欄會清空', function () {
    $player = answeringPlayer();
    $fish = Fish::factory()->create();
    islandSubmit($player, $fish, 'yayo', ['question' => 'name', 'value' => 'dalakan']);
    islandSubmit($player, $fish, 'yayo', ['question' => 'food_category', 'value' => 'oyod']);

    test()->actingAs($player)->post("/fish/{$fish->id}/responses", ['tribe' => 'yayo', 'name' => 'dalakan'])->assertSessionHasNoErrors();

    expect(islandRow($player, $fish, 'yayo')->food_category)->toBeNull();
});

it('among 魚頁送出叫法與分類都空時，這一筆已有處理方式就不擋：清空兩欄、保留處理方式', function () {
    $player = answeringPlayer();
    $fish = Fish::factory()->create();
    islandSubmit($player, $fish, 'yayo', ['question' => 'name', 'value' => 'dalakan']);
    islandSubmit($player, $fish, 'yayo', ['question' => 'processing_method', 'value' => '去魚鱗']);

    test()->actingAs($player)->post("/fish/{$fish->id}/responses", ['tribe' => 'yayo', 'name' => '', 'food_category' => ''])
        ->assertSessionHasNoErrors();

    $row = islandRow($player, $fish, 'yayo');
    expect($row->name)->toBeNull()
        ->and($row->food_category)->toBeNull()
        ->and($row->processing_method)->toBe('去魚鱗');
});

it('among 魚頁刪錄音後還有處理方式時，保留這一筆', function () {
    deletableRecordingStorage();
    $player = answeringPlayer();
    $fish = Fish::factory()->create();
    islandSubmit($player, $fish, 'yayo', ['question' => 'processing_method', 'value' => '不食用']);
    FishTribeResponse::where('fish_id', $fish->id)->update(['recording_filename' => 'a.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.000001', 'recording_seconds' => 5]);

    test()->actingAs($player)->delete("/fish/{$fish->id}/responses/recording", ['tribe' => 'yayo'])->assertSessionHasNoErrors();

    $row = islandRow($player, $fish, 'yayo');
    expect($row)->not->toBeNull()
        ->and($row->processing_method)->toBe('不食用')
        ->and($row->recording_filename)->toBeNull();
});

it('among 魚頁送出後魚島再答一題，among 的兩欄與錄音不變', function () {
    $player = answeringPlayer();
    $fish = Fish::factory()->create();
    test()->actingAs($player)->post("/fish/{$fish->id}/responses", ['tribe' => 'yayo', 'name' => 'dalakan', 'food_category' => 'oyod']);
    FishTribeResponse::where('fish_id', $fish->id)->update(['recording_filename' => 'a.m4a', 'recording_submitted_at' => '2026-11-21 10:00:00.000001', 'recording_seconds' => 5]);

    islandSubmit($player, $fish, 'yayo', ['question' => 'processing_method', 'value' => '剝皮']);

    $row = islandRow($player, $fish, 'yayo');
    expect($row->name)->toBe('dalakan')
        ->and($row->food_category)->toBe('oyod')
        ->and($row->recording_filename)->toBe('a.m4a')
        ->and($row->processing_method)->toBe('剝皮');
});

// AC 3：都不對／補充
it('補充的叫法去前後空白（含全形空白）後存下', function () {
    $player = answeringPlayer();
    $fish = Fish::factory()->create();

    islandSubmit($player, $fish, 'yayo', ['question' => 'name', 'value' => "　 ararow \t"])->assertSessionHasNoErrors();

    expect(islandRow($player, $fish, 'yayo')->name)->toBe('ararow');
});

it('補充的叫法超過 60 字時拒絕，資料不變', function () {
    $player = answeringPlayer();
    $fish = Fish::factory()->create();

    islandSubmit($player, $fish, 'yayo', ['question' => 'name', 'value' => str_repeat('a', 61)])
        ->assertSessionHasErrors(['value' => '叫法最多 60 字']);
    islandSubmit($player, $fish, 'yayo', ['question' => 'name', 'value' => str_repeat('a', 60)])->assertSessionHasNoErrors();

    expect(islandRow($player, $fish, 'yayo')->name)->toBe(str_repeat('a', 60));
});

it('補充的叫法去空白後是空的時拒絕，原本的回答不變', function (string $value) {
    $player = answeringPlayer();
    $fish = Fish::factory()->create();
    islandSubmit($player, $fish, 'yayo', ['question' => 'name', 'value' => 'dalakan']);

    islandSubmit($player, $fish, 'yayo', ['question' => 'name', 'value' => $value])
        ->assertSessionHasErrors(['value' => '請填叫法，或按取消']);

    expect(islandRow($player, $fish, 'yayo')->name)->toBe('dalakan');
})->with(['', '   ', '　　']);

it('補充的叫法是「我不知道」時拒絕，提示可以不用回答', function (string $value) {
    $player = answeringPlayer();
    $fish = Fish::factory()->create();

    islandSubmit($player, $fish, 'yayo', ['question' => 'name', 'value' => $value])
        ->assertSessionHasErrors(['value' => '不知道的話可以不用回答']);

    expect(FishTribeResponse::count())->toBe(0);
})->with(['我不知道', ' 我不知道　']);

it('選的寫法在送出前已經沒有人用，照樣接受', function () {
    $player = answeringPlayer();
    $other = answeringPlayer('yayo', '阿浪');
    $fish = Fish::factory()->create();
    islandSubmit($other, $fish, 'yayo', ['question' => 'name', 'value' => 'arayo']);
    islandSubmit($other, $fish, 'yayo', ['question' => 'name', 'value' => 'dalakan']);

    islandSubmit($player, $fish, 'yayo', ['question' => 'name', 'value' => 'arayo'])->assertSessionHasNoErrors();

    expect(islandRow($player, $fish, 'yayo')->name)->toBe('arayo');
});

// AC 8：送出限流
it('同一帳號每分鐘最多送出 30 次，超過回 429 且資料不變', function () {
    $player = answeringPlayer();
    $fish = Fish::factory()->create();

    for ($i = 0; $i < 30; $i++) {
        islandSubmit($player, $fish, 'yayo', ['question' => 'food_category', 'value' => 'oyod'])->assertRedirect();
    }
    islandSubmit($player, $fish, 'yayo', ['question' => 'food_category', 'value' => 'rahet'])->assertStatus(429);

    expect(islandRow($player, $fish, 'yayo')->food_category)->toBe('oyod');
});

it('已登入但沒有角色的帳號送出超過 30 次，仍導到角色設定頁、不寫入', function () {
    $noCharacter = User::factory()->create();
    $fish = Fish::factory()->create();

    for ($i = 0; $i < 31; $i++) {
        islandSubmit($noCharacter, $fish, 'yayo', ['question' => 'food_category', 'value' => 'oyod'])->assertRedirect('/island/character');
    }

    expect(FishTribeResponse::count())->toBe(0);
});

it('限流以帳號計：別人送出不受我的次數影響', function () {
    $player = answeringPlayer();
    $other = answeringPlayer('yayo', '阿浪');
    $fish = Fish::factory()->create();

    for ($i = 0; $i < 30; $i++) {
        islandSubmit($player, $fish, 'yayo', ['question' => 'food_category', 'value' => 'oyod']);
    }

    islandSubmit($other, $fish, 'yayo', ['question' => 'food_category', 'value' => 'oyod'])->assertRedirect();
});

/** 手建一筆回答（直接寫表，時間固定），回答者部落預設是角色的部落 */
function islandAnswerRow(User $user, Fish $fish, string $tribe, array $answer, string $createdAt = '2026-11-25 10:00:00'): FishTribeResponse
{
    $row = new FishTribeResponse(['user_id' => $user->id, 'fish_id' => $fish->id, 'tribe' => $tribe, ...$answer]);
    $row->created_at = $createdAt;
    $row->save();

    return $row;
}

function islandPage(User $user, Fish $fish, string $tribe)
{
    return test()->actingAs($user)->get("/island/tribes/{$tribe}/fish/{$fish->id}");
}

/** 作答頁某一題的資料 */
function islandQuestion(User $user, Fish $fish, string $tribe, string $key): array
{
    $questions = islandPage($user, $fish, $tribe)->assertOk()->viewData('page')['props']['questions'];

    return collect($questions)->firstWhere('key', $key);
}

function choiceOf(array $question, string $value): ?array
{
    return collect($question['result']['choices'])->firstWhere('value', $value);
}

// AC 1：作答頁
it('有角色的人開作答頁：三題依序是叫法、飲食分類、處理方式，帶選項與我的回答', function () {
    $player = answeringPlayer();
    $fish = Fish::factory()->create(['name' => 'dalakan']);
    islandAnswerRow($player, $fish, 'yayo', ['food_category' => 'oyod', 'respondent_tribe' => 'yayo']);

    islandPage($player, $fish, 'yayo')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Island/Fish')
            ->where('tribe', 'yayo')
            ->where('fish.id', $fish->id)
            ->where('fish.name', 'dalakan')
            ->where('character.name', '阿海')
            ->where('name_max_length', 60)
            ->where('questions.0.key', 'name')
            ->where('questions.0.my_answer', null)
            ->where('questions.1.key', 'food_category')
            ->where('questions.1.options', ['oyod', 'rahet', '不分類', '不食用', 'kakanen no rarakeh'])
            ->where('questions.1.my_answer', 'oyod')
            ->where('questions.2.key', 'processing_method')
            ->where('questions.2.options', ['去魚鱗', '不去魚鱗', '剝皮', '不食用', '我不知道'])
            ->where('questions.2.my_answer', null));
});

it('叫法選項＝正式魚名＋這個部落已有的寫法（同一種寫法只列一次），不含別部落的寫法', function () {
    $player = answeringPlayer();
    $fish = Fish::factory()->create(['name' => 'dalakan']);
    islandAnswerRow(answeringPlayer('yayo', '甲'), $fish, 'yayo', ['name' => 'Dalakan ']);
    islandAnswerRow(answeringPlayer('yayo', '乙'), $fish, 'yayo', ['name' => 'arayo']);
    islandAnswerRow(answeringPlayer('yayo', '丙'), $fish, 'yayo', ['name' => 'ARAYO']);
    islandAnswerRow(answeringPlayer('yayo', '丁'), $fish, 'iratay', ['name' => 'ilek']);

    expect(islandQuestion($player, $fish, 'yayo', 'name')['options'])->toBe(['dalakan', 'arayo']);
});

it('正式魚名是「我不知道」時不列進叫法選項，魚名回傳 null', function () {
    $player = answeringPlayer();
    $fish = Fish::factory()->create(['name' => '我不知道']);
    islandAnswerRow(answeringPlayer('yayo', '甲'), $fish, 'yayo', ['name' => 'arayo']);

    islandPage($player, $fish, 'yayo')->assertInertia(fn ($page) => $page
        ->where('fish.name', null)
        ->where('questions.0.options', ['arayo']));
});

it('作答頁：魚不存在、已軟刪除或部落不合法回 404；沒有角色導到角色設定頁', function () {
    $player = answeringPlayer();
    $deleted = Fish::factory()->create();
    $deleted->delete();
    $fish = Fish::factory()->create();

    test()->actingAs($player)->get('/island/tribes/yayo/fish/999999')->assertNotFound();
    islandPage($player, $deleted, 'yayo')->assertNotFound();
    islandPage($player, $fish, 'nope')->assertNotFound();
    islandPage(User::factory()->create(), $fish, 'yayo')->assertRedirect('/island/character');
    test()->get("/island/tribes/yayo/fish/{$fish->id}")->assertRedirect('/island/character');
});

// AC 4：多數
it('回答人數 ≥3 且第一名嚴格多於第二名：第一名是多數', function () {
    $fish = Fish::factory()->create();
    foreach (['oyod', 'oyod', 'rahet'] as $i => $category) {
        islandAnswerRow(answeringPlayer('yayo', "人{$i}"), $fish, 'yayo', ['food_category' => $category]);
    }

    $result = islandQuestion(answeringPlayer(), $fish, 'yayo', 'food_category')['result'];
    expect($result['total'])->toBe(3)
        ->and($result['majority'])->toBe('oyod')
        ->and(choiceOf(['result' => $result], 'oyod')['count'])->toBe(2)
        ->and(choiceOf(['result' => $result], 'rahet')['count'])->toBe(1)
        ->and(choiceOf(['result' => $result], '不分類')['count'])->toBe(0);
});

it('第一名與第二名同票、或三方同票：沒有多數', function (array $categories) {
    $fish = Fish::factory()->create();
    foreach ($categories as $i => $category) {
        islandAnswerRow(answeringPlayer('yayo', "人{$i}"), $fish, 'yayo', ['food_category' => $category]);
    }

    expect(islandQuestion(answeringPlayer(), $fish, 'yayo', 'food_category')['result']['majority'])->toBeNull();
})->with([
    '2 比 2' => [['oyod', 'oyod', 'rahet', 'rahet']],
    '1 比 1 比 1' => [['oyod', 'rahet', '不分類']],
]);

it('回答人數不到 3：沒有多數，人數照算；0 人時人數 0', function () {
    $fish = Fish::factory()->create();
    islandAnswerRow(answeringPlayer('yayo', '甲'), $fish, 'yayo', ['food_category' => 'oyod']);
    islandAnswerRow(answeringPlayer('yayo', '乙'), $fish, 'yayo', ['food_category' => 'oyod']);
    $viewer = answeringPlayer();

    $category = islandQuestion($viewer, $fish, 'yayo', 'food_category')['result'];
    $processing = islandQuestion($viewer, $fish, 'yayo', 'processing_method')['result'];
    expect($category['total'])->toBe(2)->and($category['majority'])->toBeNull()
        ->and($processing['total'])->toBe(0)->and($processing['majority'])->toBeNull();
});

it('「我不知道」可以是處理方式的多數', function () {
    $fish = Fish::factory()->create();
    foreach (['去魚鱗', '我不知道', '我不知道', '我不知道'] as $i => $method) {
        islandAnswerRow(answeringPlayer('yayo', "人{$i}"), $fish, 'yayo', ['processing_method' => $method]);
    }

    expect(islandQuestion(answeringPlayer(), $fish, 'yayo', 'processing_method')['result']['majority'])->toBe('我不知道');
});

it('三題分開算：沒填那一題的人不算進該題人數', function () {
    $fish = Fish::factory()->create();
    islandAnswerRow(answeringPlayer('yayo', '甲'), $fish, 'yayo', ['name' => 'arayo', 'food_category' => 'oyod']);
    islandAnswerRow(answeringPlayer('yayo', '乙'), $fish, 'yayo', ['processing_method' => '剝皮']);
    $viewer = answeringPlayer();

    expect(islandQuestion($viewer, $fish, 'yayo', 'name')['result']['total'])->toBe(1)
        ->and(islandQuestion($viewer, $fish, 'yayo', 'food_category')['result']['total'])->toBe(1)
        ->and(islandQuestion($viewer, $fish, 'yayo', 'processing_method')['result']['total'])->toBe(1);
});

it('叫法的多數：同一種寫法合併計算，依人數多到少列出', function () {
    $fish = Fish::factory()->create(['name' => 'dalakan']);
    foreach (['arayo', 'ARAYO ', 'dalakan', 'arayo'] as $i => $name) {
        islandAnswerRow(answeringPlayer('yayo', "人{$i}"), $fish, 'yayo', ['name' => $name]);
    }

    $result = islandQuestion(answeringPlayer(), $fish, 'yayo', 'name')['result'];
    expect($result['majority'])->toBe('arayo')
        ->and(array_column($result['choices'], 'count'))->toBe([3, 1])
        ->and(array_column($result['choices'], 'value'))->toBe(['arayo', 'dalakan']);
});

it('只算這個部落、這條魚', function () {
    $fish = Fish::factory()->create();
    $otherFish = Fish::factory()->create();
    islandAnswerRow(answeringPlayer('yayo', '甲'), $fish, 'yayo', ['food_category' => 'oyod']);
    islandAnswerRow(answeringPlayer('yayo', '乙'), $fish, 'iratay', ['food_category' => 'oyod']);
    islandAnswerRow(answeringPlayer('yayo', '丙'), $otherFish, 'yayo', ['food_category' => 'oyod']);

    expect(islandQuestion(answeringPlayer(), $fish, 'yayo', 'food_category')['result']['total'])->toBe(1);
});

// AC 5：其中本部落 N 人
it('其中本部落 N 人：只算回答者自己的部落是這個部落的；別部落、訪客、沒有魚島角色的照樣算進選項人數', function () {
    $fish = Fish::factory()->create();
    islandAnswerRow(answeringPlayer('yayo', '本部落'), $fish, 'yayo', ['food_category' => 'oyod', 'respondent_tribe' => 'yayo']);
    islandAnswerRow(answeringPlayer('iratay', '外部落'), $fish, 'yayo', ['food_category' => 'oyod', 'respondent_tribe' => 'iratay']);
    $guest = IslandCharacter::createGuest('訪客甲', 'yayo');
    islandAnswerRow($guest, $fish, 'yayo', ['food_category' => 'oyod', 'respondent_tribe' => 'yayo']);
    islandAnswerRow(User::factory()->create(['role' => 'viewer']), $fish, 'yayo', ['food_category' => 'oyod']);

    $result = islandQuestion(answeringPlayer(), $fish, 'yayo', 'food_category')['result'];
    expect(choiceOf(['result' => $result], 'oyod')['count'])->toBe(4)
        ->and($result['own_tribe_count'])->toBe(2);
});

it('其中本部落為 0 時照樣回傳 0', function () {
    $fish = Fish::factory()->create();
    islandAnswerRow(answeringPlayer('iratay', '外部落'), $fish, 'yayo', ['food_category' => 'oyod', 'respondent_tribe' => 'iratay']);

    expect(islandQuestion(answeringPlayer(), $fish, 'yayo', 'food_category')['result']['own_tribe_count'])->toBe(0);
});

// AC 6：看是誰答的
it('回答者清單：角色名＋回答時記下的部落、訪客標記，依第一次回答時間排序，行數等於人數', function () {
    $fish = Fish::factory()->create();
    $late = answeringPlayer('iratay', '晚到');
    $early = answeringPlayer('yayo', '早到');
    $guest = IslandCharacter::createGuest('訪客甲', 'imowrod');
    islandAnswerRow($late, $fish, 'yayo', ['food_category' => 'oyod', 'respondent_tribe' => 'iratay'], '2026-11-25 12:00:00');
    islandAnswerRow($early, $fish, 'yayo', ['food_category' => 'oyod', 'respondent_tribe' => 'yayo'], '2026-11-25 09:00:00');
    islandAnswerRow($guest, $fish, 'yayo', ['food_category' => 'oyod', 'respondent_tribe' => 'imowrod'], '2026-11-25 10:00:00');

    $oyod = choiceOf(islandQuestion(answeringPlayer(), $fish, 'yayo', 'food_category'), 'oyod');
    expect($oyod['responders'])->toBe([
        ['name' => '早到', 'tribe' => 'yayo', 'is_guest' => false],
        ['name' => '訪客甲', 'tribe' => 'imowrod', 'is_guest' => true],
        ['name' => '晚到', 'tribe' => 'iratay', 'is_guest' => false],
    ])->and(count($oyod['responders']))->toBe($oyod['count']);
});

it('沒有魚島角色的回答者：不給名字、不給部落（畫面顯示「部落未填」），不帶 users.name', function () {
    $fish = Fish::factory()->create();
    islandAnswerRow(User::factory()->create(['role' => 'viewer', 'name' => '王小明']), $fish, 'yayo', ['food_category' => 'oyod']);

    $page = islandPage(answeringPlayer(), $fish, 'yayo');
    $oyod = choiceOf(collect($page->viewData('page')['props']['questions'])->firstWhere('key', 'food_category'), 'oyod');
    expect($oyod['responders'])->toBe([['name' => null, 'tribe' => null, 'is_guest' => false]])
        ->and($page->getContent())->not->toContain('王小明');
});

it('在 among 答過、之後才建角色的人：給角色名、部落為空，直到在魚島送出才補上', function () {
    $fish = Fish::factory()->create();
    $user = User::factory()->create(['role' => 'viewer']);
    islandAnswerRow($user, $fish, 'yayo', ['food_category' => 'oyod']);
    IslandCharacter::create(['user_id' => $user->id, 'name' => '後來的', 'tribe' => 'iratay']);
    $viewer = answeringPlayer();

    expect(choiceOf(islandQuestion($viewer, $fish, 'yayo', 'food_category'), 'oyod')['responders'])
        ->toBe([['name' => '後來的', 'tribe' => null, 'is_guest' => false]]);

    islandSubmit($user, $fish, 'yayo', ['question' => 'processing_method', 'value' => '剝皮']);

    expect(choiceOf(islandQuestion($viewer, $fish, 'yayo', 'food_category'), 'oyod')['responders'])
        ->toBe([['name' => '後來的', 'tribe' => 'iratay', 'is_guest' => false]]);
});

it('改答不改變在清單裡的位置', function () {
    $fish = Fish::factory()->create();
    $first = answeringPlayer('yayo', '先答');
    $second = answeringPlayer('yayo', '後答');
    islandAnswerRow($first, $fish, 'yayo', ['food_category' => 'rahet'], '2026-11-25 09:00:00');
    islandAnswerRow($second, $fish, 'yayo', ['food_category' => 'oyod'], '2026-11-25 10:00:00');

    islandSubmit($first, $fish, 'yayo', ['question' => 'food_category', 'value' => 'oyod']);

    expect(array_column(choiceOf(islandQuestion(answeringPlayer(), $fish, 'yayo', 'food_category'), 'oyod')['responders'], 'name'))
        ->toBe(['先答', '後答']);
});

// AC 9：下一條魚
it('下一條魚＝編號比這條大、未軟刪除的魚裡最小的；最後一條沒有下一條', function () {
    $player = answeringPlayer();
    $current = Fish::factory()->create();
    $skipped = Fish::factory()->create();
    $skipped->delete();
    $next = Fish::factory()->create();

    islandPage($player, $current, 'yayo')->assertInertia(fn ($page) => $page->where('next_fish_id', $next->id));
    islandPage($player, $next, 'yayo')->assertInertia(fn ($page) => $page->where('next_fish_id', null));
});

it('among 送出的叫法「我不知道」不列進魚島叫法選項、不算進叫法人數與多數', function () {
    $fish = Fish::factory()->create(['name' => 'dalakan']);
    foreach (['甲', '乙', '丙'] as $name) {
        $user = answeringPlayer('yayo', $name);
        test()->actingAs($user)->post("/fish/{$fish->id}/responses", ['tribe' => 'yayo', 'name' => ' 我不知道', 'food_category' => 'oyod'])->assertSessionHasNoErrors();
    }

    $question = islandQuestion(answeringPlayer(), $fish, 'yayo', 'name');
    expect($question['options'])->toBe(['dalakan'])
        ->and($question['result']['total'])->toBe(0)
        ->and($question['result']['majority'])->toBeNull()
        ->and(array_column($question['result']['choices'], 'value'))->not->toContain('我不知道');
});

it('叫法結果也列出正式魚名，沒人選時人數 0', function () {
    $fish = Fish::factory()->create(['name' => 'dalakan']);
    islandAnswerRow(answeringPlayer('yayo', '甲'), $fish, 'yayo', ['name' => 'arayo']);

    $choices = islandQuestion(answeringPlayer(), $fish, 'yayo', 'name')['result']['choices'];
    expect(array_map(fn ($c) => [$c['value'], $c['count']], $choices))->toBe([['arayo', 1], ['dalakan', 0]]);
});
