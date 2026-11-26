<?php

use App\Models\Fish;
use App\Models\FishTribeResponse;
use App\Models\IslandCharacter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function spotPlayer(string $tribe = 'yayo'): User
{
    $user = User::factory()->create();
    IslandCharacter::create(['user_id' => $user->id, 'name' => '阿海', 'tribe' => $tribe]);

    return $user;
}

/** 某部落、某條魚的回答。$people＝[分類, 食用] 的清單，每一項由不同的人回答（null＝不答這題）。 */
function spotAnswers(Fish $fish, string $tribe, array $people): void
{
    foreach ($people as [$category, $method]) {
        FishTribeResponse::create([
            'user_id' => User::factory()->create()->id,
            'fish_id' => $fish->id,
            'tribe' => $tribe,
            'food_category' => $category,
            'processing_method' => $method,
        ]);
    }
}

/** 指定編號建魚：分配規則看編號，所以測試不靠自動編號。 */
function spotFish(int ...$ids): array
{
    return array_map(fn (int $id) => Fish::factory()->create(['id' => $id]), $ids);
}

function spotPageProps($test, string $spot, string $tribe = 'yayo', int $page = 1): array
{
    return $test->get("/island/tribes/{$tribe}/spots/{$spot}?page={$page}")->assertOk()->inertiaProps();
}

function spotFishIds(array $props): array
{
    return array_column($props['fish'], 'id');
}

// AC 1：四個地點與分配
it('魚依編號除以 4 的餘數分到地點：餘 1 主屋、餘 2 靠背石、餘 3 曬魚庭院、餘 0 涼亭', function () {
    spotFish(1, 2, 3, 4, 5, 6, 7, 8, 9);
    $this->actingAs(spotPlayer());

    expect(spotFishIds(spotPageProps($this, 'main-house')))->toBe([1, 5, 9])
        ->and(spotFishIds(spotPageProps($this, 'backrest-stone')))->toBe([2, 6])
        ->and(spotFishIds(spotPageProps($this, 'drying-yard')))->toBe([3, 7])
        ->and(spotFishIds(spotPageProps($this, 'pavilion')))->toBe([4, 8]);
});

it('地點帶代碼、名稱與 NPC 稱呼', function () {
    $this->actingAs(spotPlayer());

    expect(spotPageProps($this, 'main-house')['spot'])->toBe(['code' => 'main-house', 'name' => '主屋', 'npc' => '阿姨'])
        ->and(spotPageProps($this, 'backrest-stone')['spot'])->toBe(['code' => 'backrest-stone', 'name' => '靠背石', 'npc' => '爺爺'])
        ->and(spotPageProps($this, 'drying-yard')['spot'])->toBe(['code' => 'drying-yard', 'name' => '曬魚庭院', 'npc' => '叔叔'])
        ->and(spotPageProps($this, 'pavilion')['spot'])->toBe(['code' => 'pavilion', 'name' => '涼亭', 'npc' => '年輕人']);
});

it('刪除或新增魚不會讓其他魚換地點；軟刪除的魚不出現', function () {
    [, , , , $five] = spotFish(1, 2, 3, 4, 5);
    $this->actingAs(spotPlayer());
    $five->delete();
    spotFish(13);

    expect(spotFishIds(spotPageProps($this, 'main-house')))->toBe([1, 13])
        ->and(spotFishIds(spotPageProps($this, 'backrest-stone')))->toBe([2]);
});

// AC 3：地點清單
it('不在清單裡的地點回 404', function () {
    $this->actingAs(spotPlayer());

    $this->get('/island/tribes/yayo/spots/beach')->assertNotFound();
});

it('不在清單裡的部落回 404', function () {
    $this->actingAs(spotPlayer());

    $this->get('/island/tribes/unknown/spots/main-house')->assertNotFound();
});

it('沒有魚島角色的人開地點清單被導到角色設定頁', function () {
    $this->get('/island/tribes/yayo/spots/main-house')->assertRedirect('/island/character');
});

it('排序：有結果的在前，接著只有一題有結果，最後兩題都沒結果；同組回答筆數多的在前，一樣依編號', function () {
    [$none, $oneFew, $bothFew, $oneMany, $bothMany, $bothFewLater] = spotFish(1, 5, 9, 13, 17, 21);
    spotAnswers($bothFew, 'yayo', [['oyod', '去魚鱗']]);
    spotAnswers($bothFewLater, 'yayo', [['rahet', '剝皮']]);
    spotAnswers($bothMany, 'yayo', [['oyod', '去魚鱗'], ['oyod', null]]);
    spotAnswers($oneFew, 'yayo', [['oyod', null]]);
    spotAnswers($oneMany, 'yayo', [[null, '剝皮'], [null, '剝皮'], [null, '去魚鱗']]);
    $this->actingAs(spotPlayer());

    expect(spotFishIds(spotPageProps($this, 'main-house')))->toBe([
        $bothMany->id, $bothFew->id, $bothFewLater->id, $oneMany->id, $oneFew->id, $none->id,
    ]);
});

it('排序只看這個部落；「我不知道」、空值、清單外的值都不算結果也不算筆數', function () {
    [$otherTribe, $unknownOnly, $invalid, $real] = spotFish(1, 5, 9, 13);
    spotAnswers($otherTribe, 'iratay', [['oyod', '去魚鱗'], ['oyod', '去魚鱗']]);
    spotAnswers($unknownOnly, 'yayo', [[null, '我不知道'], [null, '我不知道']]);
    spotAnswers($invalid, 'yayo', [['?', '尚未紀錄'], ['', ''], ['OYOD', '去魚鱗 ']]);
    spotAnswers($real, 'yayo', [['oyod', null]]);
    $this->actingAs(spotPlayer());

    $props = spotPageProps($this, 'main-house');

    expect(spotFishIds($props))->toBe([$real->id, $otherTribe->id, $unknownOnly->id, $invalid->id])
        ->and(array_column($props['fish'], 'results'))->toBe([null, null, null, null]);
});

it('每頁 20 條，排序跨頁成立', function () {
    $fishes = spotFish(...array_map(fn (int $n) => $n * 4 + 1, range(0, 20)));
    spotAnswers($fishes[20], 'yayo', [['oyod', '去魚鱗']]);
    $this->actingAs(spotPlayer());

    $first = spotPageProps($this, 'main-house');
    $second = spotPageProps($this, 'main-house', page: 2);

    expect($first['fish'])->toHaveCount(20)
        ->and($first['fish'][0]['id'])->toBe($fishes[20]->id)
        ->and($first['has_more_pages'])->toBeTrue()
        ->and(spotFishIds($second))->toBe([$fishes[19]->id])
        ->and($second['page'])->toBe(2)
        ->and($second['has_more_pages'])->toBeFalse();
});

it('魚卡帶編號、魚名、照片網址；魚名是「我不知道」時不當成魚名', function () {
    [$named] = spotFish(1);
    Fish::factory()->create(['id' => 5, 'name' => '我不知道']);
    $named->update(['name' => '浪人鰺']);
    $this->actingAs(spotPlayer());

    $fish = spotPageProps($this, 'main-house')['fish'];

    expect($fish[0])->toMatchArray(['id' => 1, 'name' => '浪人鰺', 'image_url' => $named->fresh()->display_image_url])
        ->and($fish[1]['name'])->toBeNull();
});

it('兩題都有結果的魚卡帶各部落、兩題各選項的回答筆數；只有一題有結果的不帶', function () {
    [$both, $one] = spotFish(1, 5);
    spotAnswers($both, 'yayo', [['oyod', '去魚鱗'], ['rahet', '我不知道']]);
    spotAnswers($both, 'iratay', [['oyod', null]]);
    spotAnswers($one, 'yayo', [['oyod', null]]);
    $this->actingAs(spotPlayer());

    $fish = spotPageProps($this, 'main-house')['fish'];

    expect($fish[0]['results'])->toBe([
        'food_category' => [
            'iratay' => [['value' => 'oyod', 'count' => 1]],
            'yayo' => [['value' => 'oyod', 'count' => 1], ['value' => 'rahet', 'count' => 1]],
        ],
        'processing_method' => [
            'yayo' => [['value' => '去魚鱗', 'count' => 1], ['value' => '我不知道', 'count' => 1]],
        ],
    ])->and($fish[1]['results'])->toBeNull();
});

it('地點清單帶選項清單、部落清單與「我不知道」，給畫面配色與細節視窗', function () {
    $this->actingAs(spotPlayer());

    $props = spotPageProps($this, 'main-house', 'iratay');

    expect($props['tribe'])->toBe('iratay')
        ->and($props['tribes'])->toBe(config('fish_options.tribes'))
        ->and($props['result_options'])->toBe(FishTribeResponse::islandResultOptions())
        ->and($props['unknown_processing_method'])->toBe('我不知道')
        ->and($props['character'])->toBe(['name' => '阿海', 'tribe' => 'yayo']);
});

it('這個地點沒有魚時不論頁碼都是 no_fish（其他地點有魚也一樣）', function () {
    spotFish(2);
    $this->actingAs(spotPlayer());

    expect(spotPageProps($this, 'main-house')['empty_reason'])->toBe('no_fish')
        ->and(spotPageProps($this, 'main-house', page: 3)['empty_reason'])->toBe('no_fish');
});

it('這個地點的魚全部軟刪除時是 no_fish', function () {
    [$fish] = spotFish(1);
    $fish->delete();
    $this->actingAs(spotPlayer());

    expect(spotPageProps($this, 'main-house')['empty_reason'])->toBe('no_fish');
});

it('有魚但頁碼超過最後一頁是 no_more', function () {
    spotFish(1);
    $this->actingAs(spotPlayer());

    $props = spotPageProps($this, 'main-house', page: 2);

    expect($props['empty_reason'])->toBe('no_more')
        ->and($props['fish'])->toBe([])
        ->and($props['has_more_pages'])->toBeFalse();
});

it('有魚的頁面 empty_reason 是 null', function () {
    spotFish(1);
    $this->actingAs(spotPlayer());

    expect(spotPageProps($this, 'main-house')['empty_reason'])->toBeNull();
});

// AC 5：要問的那一題
it('要問的那一題：依叫法、分類、處理方式的順序，第一個缺的；三題都不缺時為 null', function () {
    [$unnamedBoth, $noCategory, $categoryOnly, $methodOnly, $both, $unknownMethod, $unnamedNone] = spotFish(1, 5, 9, 13, 17, 21, 25);
    $unnamedBoth->update(['name' => '我不知道']);
    $unnamedNone->update(['name' => '我不知道']);
    foreach ([$noCategory, $categoryOnly, $methodOnly, $both, $unknownMethod] as $fish) {
        $fish->update(['name' => '浪人鰺']);
    }
    spotAnswers($unnamedBoth, 'yayo', [['oyod', '去魚鱗']]);
    spotAnswers($categoryOnly, 'yayo', [['oyod', null]]);
    spotAnswers($methodOnly, 'yayo', [[null, '剝皮']]);
    spotAnswers($both, 'yayo', [['rahet', '剝皮']]);
    spotAnswers($unknownMethod, 'yayo', [['oyod', '我不知道']]);
    spotAnswers($noCategory, 'iratay', [['oyod', '去魚鱗']]);
    $this->actingAs(spotPlayer());

    $ask = collect(spotPageProps($this, 'main-house')['fish'])->pluck('ask', 'id');

    expect($ask[$unnamedBoth->id])->toBe('name')
        ->and($ask[$unnamedNone->id])->toBe('name')
        ->and($ask[$noCategory->id])->toBe('food_category')
        ->and($ask[$methodOnly->id])->toBe('food_category')
        ->and($ask[$categoryOnly->id])->toBe('processing_method')
        ->and($ask[$unknownMethod->id])->toBe('processing_method')
        ->and($ask[$both->id])->toBeNull();
});
