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
        ->assertSessionHasErrors(['value' => '請填叫法，或按跳過']);

    expect(islandRow($player, $fish, 'yayo')->name)->toBe('dalakan');
})->with(['', '   ', '　　']);

it('補充的叫法是「我不知道」時拒絕，提示按跳過', function (string $value) {
    $player = answeringPlayer();
    $fish = Fish::factory()->create();

    islandSubmit($player, $fish, 'yayo', ['question' => 'name', 'value' => $value])
        ->assertSessionHasErrors(['value' => '不知道的話請按跳過']);

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
