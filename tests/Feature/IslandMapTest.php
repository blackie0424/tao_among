<?php

use App\Models\Fish;
use App\Models\FishTribeResponse;
use App\Models\IslandCharacter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

const ISLAND_TRIBES = ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley'];

function islandPlayer(string $tribe = 'yayo', string $name = '阿海'): User
{
    $user = User::factory()->create();
    IslandCharacter::create(['user_id' => $user->id, 'name' => $name, 'tribe' => $tribe]);

    return $user;
}

function islandAnswer(User $user, Fish $fish, string $tribe, array $answer): void
{
    FishTribeResponse::create(['user_id' => $user->id, 'fish_id' => $fish->id, 'tribe' => $tribe, ...$answer]);
}

/** #189：建一條放在主屋的魚（編號除以 4 餘 1，接在現有最大編號之後），部落頁的魚卡清單測試改在主屋的地點清單上跑。 */
function houseFish(array $attributes = []): Fish
{
    $max = (int) Fish::withTrashed()->max('id');
    $id = $max + 1 + ((4 - $max % 4) % 4);

    return Fish::factory()->create(['id' => $id, ...$attributes]);
}

/** @return \Illuminate\Support\Collection<int, Fish> */
function houseFishes(int $count): \Illuminate\Support\Collection
{
    return collect(range(1, $count))->map(fn () => houseFish());
}

// AC 1
it('有角色的人開 /island：帶 6 個部落與目前所在部落，第一次來是自己的部落', function () {
    $this->actingAs(islandPlayer('yayo'))
        ->get('/island')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Island/Home')
            ->where('tribes', ISLAND_TRIBES)
            ->where('current_tribe', 'yayo')
            ->where('character.tribe', 'yayo')
            ->where('character.name', '阿海'));
});

// AC 2
it('走到別的部落後，再開 /island 所在部落是那個部落，角色自己的部落不變', function () {
    $this->actingAs(islandPlayer('yayo'));

    $this->get('/island/tribes/iratay')->assertOk();

    $this->get('/island')
        ->assertInertia(fn ($page) => $page
            ->where('current_tribe', 'iratay')
            ->where('character.tribe', 'yayo'));
});

it('同一個 session 換成另一個角色登入，不沿用前一個角色走到的部落', function () {
    $this->actingAs(islandPlayer('yayo', '甲'));
    $this->get('/island/tribes/iratay')->assertOk();

    Auth::logout();
    $this->actingAs(islandPlayer('imowrod', '乙'));

    $this->get('/island')
        ->assertInertia(fn ($page) => $page
            ->where('current_tribe', 'imowrod')
            ->where('character.name', '乙'));
});

// AC 3
it('地點清單（原部落頁）每頁 20 條魚卡、依編號排序，可用頁碼取下一頁', function () {
    $fishes = houseFishes(21);
    $this->actingAs(islandPlayer());

    $this->get('/island/tribes/yayo/spots/main-house')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Island/Spot')
            ->where('tribe', 'yayo')
            ->has('fish', 20)
            ->where('fish.0.id', $fishes[0]->id)
            ->where('fish.19.id', $fishes[19]->id)
            ->where('has_more_pages', true)
            ->where('empty_reason', null));

    $this->get('/island/tribes/yayo/spots/main-house?page=2')
        ->assertInertia(fn ($page) => $page
            ->has('fish', 1)
            ->where('fish.0.id', $fishes[20]->id)
            ->where('page', 2)
            ->where('has_more_pages', false));
});

it('魚卡帶編號、魚名、照片網址；魚名是「我不知道」時不當成魚名', function () {
    $named = houseFish(['name' => '浪人鰺']);
    houseFish(['name' => '我不知道']);
    $this->actingAs(islandPlayer());

    $this->get('/island/tribes/yayo/spots/main-house')
        ->assertInertia(fn ($page) => $page
            ->where('fish.0.id', $named->id)
            ->where('fish.0.name', '浪人鰺')
            ->where('fish.0.image_url', $named->display_image_url)
            ->where('fish.1.name', null));
});

it('軟刪除的魚不出現在魚卡，也不算頁數', function () {
    $kept = houseFish();
    $deleted = houseFish();
    islandAnswer(User::factory()->create(), $deleted, 'yayo', ['name' => 'ilek']);
    $deleted->delete();
    $this->actingAs(islandPlayer());

    $this->get('/island/tribes/yayo/spots/main-house')
        ->assertInertia(fn ($page) => $page
            ->has('fish', 1)
            ->where('fish.0.id', $kept->id)
            ->where('has_more_pages', false));
});

// AC 4
it('不在清單裡的部落回 404，所在部落不變', function () {
    $this->actingAs(islandPlayer('yayo'));
    $this->get('/island/tribes/iratay')->assertOk();

    $this->get('/island/tribes/taipei')->assertNotFound();

    $this->get('/island')->assertInertia(fn ($page) => $page->where('current_tribe', 'iratay'));
});

// AC 5
it('沒登入的人開部落頁被導到角色設定頁', function () {
    Fish::factory()->create();

    $this->get('/island/tribes/yayo')->assertRedirect('/island/character');
});

it('登入但沒有魚島角色的人開部落頁被導到角色設定頁', function () {
    Fish::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get('/island/tribes/yayo')
        ->assertRedirect('/island/character');
});

// AC 6
it('沒有任何魚時不論頁碼都是「目前沒有魚」', function () {
    $this->actingAs(islandPlayer());

    foreach (['/island/tribes/yayo/spots/main-house', '/island/tribes/yayo/spots/main-house?page=2'] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('fish', 0)
                ->where('empty_reason', 'no_fish'));
    }
});

it('魚全部軟刪除時是「目前沒有魚」', function () {
    houseFishes(2)->each->delete();
    $this->actingAs(islandPlayer());

    $this->get('/island/tribes/yayo/spots/main-house')
        ->assertInertia(fn ($page) => $page->has('fish', 0)->where('empty_reason', 'no_fish'));
});

it('有魚但頁碼超過最後一頁是「沒有更多了」', function () {
    houseFishes(21);
    $this->actingAs(islandPlayer());

    $this->get('/island/tribes/yayo/spots/main-house?page=3')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('fish', 0)
            ->where('empty_reason', 'no_more')
            ->where('has_more_pages', false));
});

// AC 3（規格修訂第 3 次）：魚卡不帶回答資料，排序只看編號
/** #183：某部落、某條魚的結果。$people＝[分類, 食用] 的清單，每一項由不同的人回答（null＝不答這題）。 */
function islandResults(Fish $fish, string $tribe, array $people): void
{
    foreach ($people as [$category, $method]) {
        islandAnswer(User::factory()->create(), $fish, $tribe, ['food_category' => $category, 'processing_method' => $method]);
    }
}

function tribePageProps($test, string $tribe = 'yayo'): array
{
    return $test->get("/island/tribes/{$tribe}")->assertOk()->inertiaProps();
}

/** #189：部落頁的魚卡清單搬到地點清單；測試的魚都放在主屋（編號除以 4 餘 1）。 */
function spotListProps($test, string $tribe = 'yayo', int $page = 1): array
{
    return $test->get("/island/tribes/{$tribe}/spots/main-house?page={$page}")->assertOk()->inertiaProps();
}

// #183 AC 1：計算規則
it('#183 有結果的魚卡帶各部落、兩題各選項的回答筆數，部落與選項依設定的順序、0 筆不列', function () {
    $fish = houseFish();
    islandResults($fish, 'yayo', [['rahet', '剝皮'], ['oyod', '去魚鱗'], ['oyod', '去魚鱗'], ['oyod', '我不知道']]);
    islandResults($fish, 'iratay', [['kakanen no rarakeh', null]]);
    $this->actingAs(islandPlayer());

    $results = spotListProps($this)['fish'][0]['results'];

    expect($results['food_category'])->toBe([
        'iratay' => [['value' => 'kakanen no rarakeh', 'count' => 1]],
        'yayo' => [['value' => 'oyod', 'count' => 3], ['value' => 'rahet', 'count' => 1]],
    ])->and($results['processing_method'])->toBe([
        'yayo' => [['value' => '去魚鱗', 'count' => 2], ['value' => '剝皮', 'count' => 1], ['value' => '我不知道', 'count' => 1]],
    ]);
});

it('#183 人數是回答筆數：同一人替兩個部落作答各算 1；別部落與沒有魚島角色的人也算', function () {
    $fish = houseFish();
    $someone = islandPlayer('iratay', '外地人');
    islandAnswer($someone, $fish, 'yayo', ['food_category' => 'oyod', 'processing_method' => '去魚鱗', 'respondent_tribe' => 'iratay']);
    islandAnswer($someone, $fish, 'imowrod', ['food_category' => 'oyod', 'processing_method' => '去魚鱗', 'respondent_tribe' => 'iratay']);
    islandAnswer(User::factory()->create(), $fish, 'yayo', ['food_category' => 'oyod']);
    $this->actingAs(islandPlayer());

    $results = spotListProps($this)['fish'][0]['results'];

    expect($results['food_category']['yayo'])->toBe([['value' => 'oyod', 'count' => 2]])
        ->and($results['food_category']['imowrod'])->toBe([['value' => 'oyod', 'count' => 1]])
        ->and($results['processing_method']['yayo'])->toBe([['value' => '去魚鱗', 'count' => 1]]);
});

it('#183 空值、空字串、清單外的值與清單外的部落不算', function () {
    $fish = houseFish();
    islandResults($fish, 'yayo', [['oyod', '去魚鱗'], ['', ''], [null, null], ['?', '尚未紀錄']]);
    islandResults($fish, 'unknown-tribe', [['oyod', '去魚鱗']]);
    $this->actingAs(islandPlayer());

    $results = spotListProps($this)['fish'][0]['results'];

    expect($results)->toBe([
        'food_category' => ['yayo' => [['value' => 'oyod', 'count' => 1]]],
        'processing_method' => ['yayo' => [['value' => '去魚鱗', 'count' => 1]]],
    ]);
});

it('#183 地點清單（原部落頁）帶選項清單、部落清單與「我不知道」，給畫面配色與排序', function () {
    houseFish();
    $this->actingAs(islandPlayer());

    $props = spotListProps($this);

    expect($props['result_options'])->toBe([
        'food_category' => config('fish_options.response_food_categories'),
        'processing_method' => config('fish_options.response_processing_methods'),
    ])->and($props['tribes'])->toBe(ISLAND_TRIBES)
        ->and($props['unknown_processing_method'])->toBe('我不知道')
        ->and(config('fish_options.response_processing_methods'))->toContain($props['unknown_processing_method']);
});

// #183 AC 2：有結果與進度（以所在部落計）
it('#183 分類與非「我不知道」的食用都有才算有結果；只有一題、或食用只有「我不知道」不算', function () {
    [$both, $categoryOnly, $methodOnly, $unknownOnly] = houseFishes(4);
    islandResults($both, 'yayo', [['oyod', null], [null, '剝皮']]);
    islandResults($categoryOnly, 'yayo', [['oyod', null]]);
    islandResults($methodOnly, 'yayo', [[null, '剝皮']]);
    islandResults($unknownOnly, 'yayo', [['oyod', '我不知道']]);
    $this->actingAs(islandPlayer());

    $fish = collect(spotListProps($this)['fish'])->keyBy('id');

    expect($fish[$both->id]['results'])->not->toBeNull()
        ->and($fish[$categoryOnly->id]['results'])->toBeNull()
        ->and($fish[$methodOnly->id]['results'])->toBeNull()
        ->and($fish[$unknownOnly->id]['results'])->toBeNull();
});

it('#183 同一條魚只有別的部落答過：在那個部落頁有結果，在本部落頁沒有', function () {
    $fish = houseFish();
    islandResults($fish, 'iratay', [['oyod', '剝皮']]);
    $this->actingAs(islandPlayer());

    expect(spotListProps($this, 'yayo')['fish'][0]['results'])->toBeNull()
        ->and(spotListProps($this, 'iratay')['fish'][0]['results']['food_category'])->toBe(['iratay' => [['value' => 'oyod', 'count' => 1]]]);
});

it('#183 進度＝這個部落有結果的魚條數／全部未軟刪除的魚條數，各部落不同', function () {
    $fishes = houseFishes(4);
    islandResults($fishes[0], 'yayo', [['oyod', '剝皮']]);
    islandResults($fishes[1], 'yayo', [['rahet', '去魚鱗']]);
    islandResults($fishes[2], 'iratay', [['oyod', '剝皮']]);
    $deleted = houseFish();
    islandResults($deleted, 'yayo', [['oyod', '剝皮']]);
    $deleted->delete();
    $this->actingAs(islandPlayer());

    expect(tribePageProps($this, 'yayo')['progress'])->toBe(['with_result' => 2, 'total' => 4])
        ->and(tribePageProps($this, 'iratay')['progress'])->toBe(['with_result' => 1, 'total' => 4])
        ->and(tribePageProps($this, 'imowrod')['progress'])->toBe(['with_result' => 0, 'total' => 4]);
});

it('#183 沒有魚時沒有進度', function () {
    $this->actingAs(islandPlayer());

    expect(tribePageProps($this)['progress'])->toBeNull();
});

// #183 AC 3：排序（以所在部落計）
it('#183 這個部落有結果的魚排前面，各自依編號；只有別的部落答過的算沒有結果', function () {
    $fishes = houseFishes(4);
    islandResults($fishes[3], 'yayo', [['oyod', '剝皮']]);
    islandResults($fishes[1], 'yayo', [['rahet', '去魚鱗']]);
    islandResults($fishes[0], 'iratay', [['oyod', '剝皮']]);
    $this->actingAs(islandPlayer());

    expect(array_column(spotListProps($this)['fish'], 'id'))
        ->toBe([$fishes[1]->id, $fishes[3]->id, $fishes[0]->id, $fishes[2]->id]);
});

it('#183 排序跨頁成立，每頁 20 條', function () {
    $fishes = houseFishes(22);
    islandResults($fishes[21], 'yayo', [['oyod', '剝皮']]);
    $this->actingAs(islandPlayer());

    $first = spotListProps($this, 'yayo', 1);
    $second = spotListProps($this, 'yayo', 2);

    expect($first['fish'])->toHaveCount(20)
        ->and($first['fish'][0]['id'])->toBe($fishes[21]->id)
        ->and($first['fish'][1]['id'])->toBe($fishes[0]->id)
        ->and(array_column($second['fish'], 'id'))->toBe([$fishes[19]->id, $fishes[20]->id]);
});

// #183 第 1 步審查：清單外的值必須精確排除（MySQL 預設不分大小寫、尾端空白視為相同）
it('#183 大小寫不同或帶尾端空白的選項、部落不算：不算有結果、不算進度、不出現在結果', function () {
    [$badCategory, $badMethod, $badTribe] = houseFishes(3);
    islandResults($badCategory, 'yayo', [['OYOD', '剝皮'], ['oyod ', '剝皮']]);
    islandResults($badMethod, 'yayo', [['oyod', '剝皮 '], ['oyod', '去魚鱗 ']]);
    islandResults($badTribe, 'YAYO', [['oyod', '剝皮']]);
    islandResults($badTribe, 'yayo ', [['oyod', '剝皮']]);
    $this->actingAs(islandPlayer());

    expect(array_column(spotListProps($this)['fish'], 'results'))->toBe([null, null, null])
        ->and(tribePageProps($this)['progress'])->toBe(['with_result' => 0, 'total' => 3]);
});

it('#183 大小寫不同的值不影響清單內值的計數', function () {
    $fish = houseFish();
    islandResults($fish, 'yayo', [['oyod', '剝皮'], ['OYOD', '剝皮']]);
    $this->actingAs(islandPlayer());

    expect(spotListProps($this)['fish'][0]['results'])->toBe([
        'food_category' => ['yayo' => [['value' => 'oyod', 'count' => 1]]],
        'processing_method' => ['yayo' => [['value' => '剝皮', 'count' => 2]]],
    ]);
});

// #183 第 1 步審查：「我不知道」以設定為單一來源
it('#183 食用方式的「我不知道」取自設定：判定有結果與傳給畫面的是同一個值', function () {
    config([
        'fish_options.response_processing_methods' => ['去魚鱗', '說不上來'],
        'fish_options.response_unknown_processing_method' => '說不上來',
    ]);
    $fish = houseFish();
    islandResults($fish, 'yayo', [['oyod', '說不上來']]);
    $this->actingAs(islandPlayer());

    $props = spotListProps($this);

    expect($props['unknown_processing_method'])->toBe('說不上來')
        ->and($props['fish'][0]['results'])->toBeNull();
});

it('#183 設定裡的「我不知道」是回答選項之一', function () {
    expect(config('fish_options.response_processing_methods'))
        ->toContain(config('fish_options.response_unknown_processing_method'));
});

// #189 AC 2：家屋頁
it('#189 部落頁是家屋：依序帶四個地點的代碼、名稱、NPC 稱呼與 X/Y', function () {
    $this->actingAs(islandPlayer());

    $this->get('/island/tribes/iratay')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Island/Tribe')
            ->where('tribe', 'iratay')
            ->missing('fish')
            ->where('spots', [
                ['code' => 'main-house', 'name' => 'vahay', 'npc' => 'kaminan', 'with_result' => 0, 'total' => 0],
                ['code' => 'backrest-stone', 'name' => 'pananadengan', 'npc' => 'akay', 'with_result' => 0, 'total' => 0],
                ['code' => 'drying-yard', 'name' => 'zazawan', 'npc' => 'maran', 'with_result' => 0, 'total' => 0],
                ['code' => 'pavilion', 'name' => 'tagakal', 'npc' => 'kaka', 'with_result' => 0, 'total' => 0],
            ]));
});

it('#189 地點的 X＝這個部落有結果的魚條數、Y＝未軟刪除的魚條數；只有一題、別的部落、軟刪除都不算 X', function () {
    foreach ([1, 5, 9, 13, 17, 2, 6] as $id) {
        Fish::factory()->create(['id' => $id]);
    }
    islandResults(Fish::find(1), 'yayo', [['oyod', '剝皮']]);
    islandResults(Fish::find(5), 'yayo', [['oyod', null]]);
    islandResults(Fish::find(9), 'iratay', [['oyod', '剝皮']]);
    islandResults(Fish::find(13), 'yayo', [['rahet', '我不知道']]);
    islandResults(Fish::find(17), 'yayo', [['rahet', '去魚鱗']]);
    Fish::find(17)->delete();
    islandResults(Fish::find(6), 'yayo', [['oyod', '去魚鱗']]);
    $this->actingAs(islandPlayer());

    $spots = collect(tribePageProps($this, 'yayo')['spots'])->keyBy('code');

    expect($spots['main-house'])->toMatchArray(['with_result' => 1, 'total' => 4])
        ->and($spots['backrest-stone'])->toMatchArray(['with_result' => 1, 'total' => 2])
        ->and($spots['drying-yard'])->toMatchArray(['with_result' => 0, 'total' => 0])
        ->and($spots['pavilion'])->toMatchArray(['with_result' => 0, 'total' => 0])
        ->and(tribePageProps($this, 'iratay')['spots'][0])->toMatchArray(['with_result' => 1, 'total' => 4]);
});

// #200 AC 9：只記位置的寫入點
it('#200 記位置：成功回 204，再開 /island 所在部落就是它，角色自己的部落不變', function () {
    $this->actingAs(islandPlayer('yayo'));

    $this->postJson('/island/location', ['tribe' => 'ivalino'])->assertNoContent();

    $this->get('/island')
        ->assertInertia(fn ($page) => $page
            ->where('current_tribe', 'ivalino')
            ->where('character.tribe', 'yayo'));
});

it('#200 記位置：連續記兩次，以最後一次為準', function () {
    $this->actingAs(islandPlayer('yayo'));

    $this->postJson('/island/location', ['tribe' => 'ivalino'])->assertNoContent();
    $this->postJson('/island/location', ['tribe' => 'iraraley'])->assertNoContent();

    $this->get('/island')->assertInertia(fn ($page) => $page->where('current_tribe', 'iraraley'));
});

it('#200 記位置：不在清單裡的部落、沒帶部落、不是文字都回 422，所在部落不變', function (mixed $tribe) {
    $this->actingAs(islandPlayer('yayo'));
    $this->postJson('/island/location', ['tribe' => 'iratay'])->assertNoContent();

    $this->postJson('/island/location', ['tribe' => $tribe])
        ->assertStatus(422)
        ->assertJsonValidationErrors('tribe');

    $this->get('/island')->assertInertia(fn ($page) => $page->where('current_tribe', 'iratay'));
})->with([
    '清單外' => ['taipei'],
    '大小寫不同' => ['Ivalino'],
    '空字串' => [''],
    '沒帶' => [null],
    '陣列' => [['ivalino']],
]);

it('#200 記位置：沒登入的人被導到角色設定頁', function () {
    $this->post('/island/location', ['tribe' => 'ivalino'])->assertRedirect('/island/character');
});

it('#200 記位置：登入但沒有魚島角色的人被導到角色設定頁，不會記住', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/island/location', ['tribe' => 'ivalino'])
        ->assertRedirect('/island/character');

    IslandCharacter::create(['user_id' => $user->id, 'name' => '阿海', 'tribe' => 'yayo']);
    $this->get('/island')->assertInertia(fn ($page) => $page->where('current_tribe', 'yayo'));
});

it('#200 記位置：同一個 session 換成另一個角色登入，不沿用前一個角色記的部落', function () {
    $this->actingAs(islandPlayer('yayo', '甲'));
    $this->postJson('/island/location', ['tribe' => 'iratay'])->assertNoContent();

    Auth::logout();
    $this->actingAs(islandPlayer('imowrod', '乙'));

    $this->get('/island')->assertInertia(fn ($page) => $page->where('current_tribe', 'imowrod'));
});

// #200 無效起點：所在部落一定是清單裡的部落
it('#200 session 記的部落已不在清單裡：所在部落改用角色自己的部落', function () {
    $user = islandPlayer('yayo');
    $character = IslandCharacter::where('user_id', $user->id)->firstOrFail();

    $this->actingAs($user)
        ->withSession(['island.location' => ['character_id' => $character->id, 'tribe' => 'taipei']])
        ->get('/island')
        ->assertInertia(fn ($page) => $page->where('current_tribe', 'yayo'));
});

it('#200 session 記的部落與角色自己的部落都不在清單裡：所在部落用清單的第一個', function () {
    $user = islandPlayer('taipei');
    $character = IslandCharacter::where('user_id', $user->id)->firstOrFail();

    $this->actingAs($user)
        ->withSession(['island.location' => ['character_id' => $character->id, 'tribe' => 'tainan']])
        ->get('/island')
        ->assertInertia(fn ($page) => $page
            ->where('current_tribe', 'ivalino')
            ->where('character.tribe', 'taipei'));
});

it('#200 沒記過位置、角色自己的部落不在清單裡：所在部落用清單的第一個', function () {
    $this->actingAs(islandPlayer('taipei'))
        ->get('/island')
        ->assertInertia(fn ($page) => $page->where('current_tribe', 'ivalino'));
});

// #201 AC 10、12：每戶四個地點的 X/Y（進魚島時一次給）
const ISLAND_HOUSEHOLD_SPOTS = [
    ['code' => 'main-house', 'name' => 'vahay', 'npc' => 'kaminan', 'greeting' => 'kaminan kong'],
    ['code' => 'backrest-stone', 'name' => 'pananadengan', 'npc' => 'akay', 'greeting' => 'akay kong'],
    ['code' => 'drying-yard', 'name' => 'zazawan', 'npc' => 'maran', 'greeting' => 'maran kong'],
    ['code' => 'pavilion', 'name' => 'tagakal', 'npc' => 'kaka', 'greeting' => 'kaka kong'],
];

function islandHomeSpots($test): array
{
    return $test->get('/island')->assertOk()->inertiaProps()['spots'];
}

/** 這個部落第 $household 戶（1～3）、地點代碼 $code 的那一筆 */
function householdSpot(array $spots, string $tribe, int $household, string $code): array
{
    return collect($spots[$tribe])->first(fn ($spot) => $spot['household'] === $household && $spot['code'] === $code);
}

it('#201 /island 帶 6 個部落 × 12 個地點：依序第 1～3 戶，每戶 vahay、pananadengan、zazawan、tagakal，族語名稱、稱呼、問好語；沒有魚時 X、Y 都是 0', function () {
    $this->actingAs(islandPlayer());

    $spots = islandHomeSpots($this);

    expect(array_keys($spots))->toEqualCanonicalizing(ISLAND_TRIBES);
    $expected = collect([1, 2, 3])->flatMap(fn ($household) => array_map(
        fn ($spot) => ['household' => $household, ...$spot, 'with_result' => 0, 'total' => 0],
        ISLAND_HOUSEHOLD_SPOTS
    ))->all();
    foreach (ISLAND_TRIBES as $tribe) {
        expect($spots[$tribe])->toBe($expected);
    }
});

it('#201 魚依編號除以 12 的餘數分到戶與地點：1～4 第 1 戶、5～8 第 2 戶、9～11 與 0 第 3 戶；X 只算這個部落有結果的，軟刪除不算', function () {
    foreach ([1, 13, 5, 4, 12, 24, 25] as $id) {
        Fish::factory()->create(['id' => $id]);
    }
    Fish::find(25)->delete();
    islandResults(Fish::find(1), 'yayo', [['oyod', '剝皮']]);
    islandResults(Fish::find(12), 'iratay', [['oyod', '剝皮']]);
    $this->actingAs(islandPlayer());

    $spots = islandHomeSpots($this);

    expect(householdSpot($spots, 'yayo', 1, 'main-house'))->toMatchArray(['with_result' => 1, 'total' => 2])
        ->and(householdSpot($spots, 'iratay', 1, 'main-house'))->toMatchArray(['with_result' => 0, 'total' => 2])
        ->and(householdSpot($spots, 'yayo', 2, 'main-house'))->toMatchArray(['with_result' => 0, 'total' => 1])
        ->and(householdSpot($spots, 'yayo', 1, 'pavilion'))->toMatchArray(['with_result' => 0, 'total' => 1])
        ->and(householdSpot($spots, 'yayo', 3, 'pavilion'))->toMatchArray(['with_result' => 0, 'total' => 2])
        ->and(householdSpot($spots, 'iratay', 3, 'pavilion'))->toMatchArray(['with_result' => 1, 'total' => 2])
        ->and(householdSpot($spots, 'yayo', 3, 'main-house'))->toMatchArray(['with_result' => 0, 'total' => 0]);
});

it('#201 舊平面頁的地點不變：同一種地點 3 戶的 X、Y 加起來等於部落頁那個地點的 X、Y', function () {
    foreach ([1, 5, 9, 13, 2, 12] as $id) {
        Fish::factory()->create(['id' => $id]);
    }
    islandResults(Fish::find(5), 'yayo', [['oyod', '剝皮']]);
    $this->actingAs(islandPlayer());

    $spots = islandHomeSpots($this);
    $flat = collect(tribePageProps($this, 'yayo')['spots'])->keyBy('code');

    foreach (ISLAND_HOUSEHOLD_SPOTS as ['code' => $code]) {
        $households = collect([1, 2, 3])->map(fn ($household) => householdSpot($spots, 'yayo', $household, $code));
        expect($households->sum('total'))->toBe($flat[$code]['total'])
            ->and($households->sum('with_result'))->toBe($flat[$code]['with_result']);
    }
    expect($flat['main-house'])->toMatchArray(['with_result' => 1, 'total' => 4]);
});

it('#201 數字是打開那一刻的值：有人新增回答後，重新打開才變', function () {
    Fish::factory()->create(['id' => 2]);
    $this->actingAs(islandPlayer());
    expect(householdSpot(islandHomeSpots($this), 'yayo', 1, 'backrest-stone'))->toMatchArray(['with_result' => 0, 'total' => 1]);

    islandResults(Fish::find(2), 'yayo', [['oyod', '剝皮']]);

    expect(householdSpot(islandHomeSpots($this), 'yayo', 1, 'backrest-stone'))->toMatchArray(['with_result' => 1, 'total' => 1]);
});
