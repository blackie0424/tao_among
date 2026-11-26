<?php

use App\Models\Fish;
use App\Models\FishTribeResponse;
use App\Models\IslandCharacter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function talkPlayer(string $tribe = 'yayo'): User
{
    $user = User::factory()->create();
    IslandCharacter::create(['user_id' => $user->id, 'name' => '阿海', 'tribe' => $tribe]);

    return $user;
}

/** 主屋的魚（編號除以 4 餘 1）。 */
function talkFish(int $id, string $name): Fish
{
    return Fish::factory()->create(['id' => $id, 'name' => $name]);
}

/** $people＝[分類, 食用] 的清單，每一項由不同的人回答（null＝不答這題）。 */
function talkAnswers(Fish $fish, string $tribe, array $people): void
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

function talkProps($test, string $query = '', string $tribe = 'iratay', string $spot = 'main-house'): array
{
    return $test->get("/island/tribes/{$tribe}/spots/{$spot}/talk{$query}")->assertOk()->inertiaProps();
}

// AC 6：對話頁
it('帶 NPC、第 N／M 條與這條魚；順序同地點清單', function () {
    $none = talkFish(1, '魚B');
    $both = talkFish(5, '魚A');
    talkFish(2, '靠背石的魚');
    talkAnswers($both, 'iratay', [['oyod', '去魚鱗']]);
    $this->actingAs(talkPlayer());

    $first = talkProps($this);
    $second = talkProps($this, '?n=2');

    expect($first['spot'])->toBe(['code' => 'main-house', 'name' => '主屋', 'npc' => '阿姨'])
        ->and($first['n'])->toBe(1)
        ->and($first['total'])->toBe(2)
        ->and($first['fish']['id'])->toBe($both->id)
        ->and($first['fish']['name'])->toBe('魚A')
        ->and($first['fish']['image_url'])->toBe($both->display_image_url)
        ->and($second['n'])->toBe(2)
        ->and($second['fish']['id'])->toBe($none->id);
});

it('n 不是 1～M 的整數時當成 1', function () {
    talkFish(1, '魚A');
    talkFish(5, '魚B');
    $this->actingAs(talkPlayer());

    foreach (['?n=0', '?n=3', '?n=-1', '?n=abc', '?n=1.5', ''] as $query) {
        expect(talkProps($this, $query)['n'])->toBe(1, $query);
    }
});

it('這個地點沒有魚（含全部軟刪除）：fish 為 null、M＝0、NPC 說「這裡還沒有魚」', function () {
    talkFish(1, '魚A')->delete();
    talkFish(2, '靠背石的魚');
    $this->actingAs(talkPlayer());

    $props = talkProps($this);

    expect($props['fish'])->toBeNull()
        ->and($props['total'])->toBe(0)
        ->and($props['speech'])->toBe('這裡還沒有魚');
});

it('不在清單裡的地點或部落回 404；沒有角色導到角色設定頁', function () {
    $this->get('/island/tribes/iratay/spots/main-house/talk')->assertRedirect('/island/character');
    $this->actingAs(talkPlayer());
    $this->get('/island/tribes/iratay/spots/beach/talk')->assertNotFound();
    $this->get('/island/tribes/taipei/spots/main-house/talk')->assertNotFound();
});

// AC 7：NPC 說的話（逐字）
it('兩題都只有一個多數：講分類與處理方式；可以看誰說的、沒有要問的題', function () {
    $fish = talkFish(1, '魚A');
    talkAnswers($fish, 'iratay', [['oyod', '去魚鱗'], ['oyod', '去魚鱗'], ['rahet', '剝皮']]);
    talkFish(5, '魚B');
    $this->actingAs(talkPlayer());

    $props = talkProps($this);

    expect($props['speech'])->toBe('這是 魚A。我們 iratay 多數人說它是 oyod；多數人說抓回來去魚鱗吃。')
        ->and($props['fish']['ask'])->toBeNull()
        ->and($props['fish']['results'])->not->toBeNull()
        ->and($props['is_last'])->toBeFalse();
});

it('兩題都平手、其中一個是「不食用」', function () {
    $fish = talkFish(1, '魚A');
    talkAnswers($fish, 'iratay', [['oyod', '剝皮'], ['rahet', '不食用']]);
    $this->actingAs(talkPlayer());

    expect(talkProps($this)['speech'])->toBe(
        '這是 魚A。我們 iratay 有人說它是 oyod，也有一樣多人說是 rahet；怎麼處理，有人說剝皮吃，也有一樣多人說不吃。這裡的魚都講完了。'
    );
});

it('三個以上平手依設定順序全部列出；處理方式只有一個多數「不食用」時說「不吃它」', function () {
    $fish = talkFish(1, '魚A');
    talkAnswers($fish, 'iratay', [['不分類', '不食用'], ['rahet', '不食用'], ['oyod', null]]);
    $this->actingAs(talkPlayer());

    expect(talkProps($this)['speech'])->toBe(
        '這是 魚A。我們 iratay 有一樣多人分別說是 oyod、rahet、不分類；多數人說不吃它。這裡的魚都講完了。'
    );
});

it('處理方式三個以上平手', function () {
    $fish = talkFish(1, '魚A');
    talkAnswers($fish, 'iratay', [['oyod', '剝皮'], [null, '去魚鱗'], [null, '不食用']]);
    $this->actingAs(talkPlayer());

    expect(talkProps($this)['speech'])->toBe(
        '這是 魚A。我們 iratay 多數人說它是 oyod；怎麼處理，有一樣多人分別說去魚鱗吃、剝皮吃、不吃。這裡的魚都講完了。'
    );
});

it('沒有名字但分類有結果：問叫法，「我來說」去叫法', function () {
    $fish = talkFish(1, '我不知道');
    talkAnswers($fish, 'iratay', [['rahet', null]]);
    talkFish(5, '魚B');
    $this->actingAs(talkPlayer());

    $props = talkProps($this);

    expect($props['speech'])->toBe('這是 這條還沒有名字的魚。我們 iratay 多數人說它是 rahet；怎麼處理還沒有答案。它還沒有名字，你知道我們怎麼叫它嗎？')
        ->and($props['fish']['name'])->toBeNull()
        ->and($props['fish']['ask'])->toBe('name')
        ->and($props['fish']['results'])->not->toBeNull();
});

it('只有處理方式有結果：問分類', function () {
    $fish = talkFish(1, '魚A');
    talkAnswers($fish, 'iratay', [[null, '剝皮']]);
    $this->actingAs(talkPlayer());

    $props = talkProps($this);

    expect($props['speech'])->toBe('這是 魚A。我們 iratay 還沒有分類的答案；多數人說抓回來剝皮吃。你知道它算哪一類嗎？這裡的魚都講完了。')
        ->and($props['fish']['ask'])->toBe('food_category');
});

it('只有分類有結果：問處理方式', function () {
    $fish = talkFish(1, '魚A');
    talkAnswers($fish, 'iratay', [['oyod', null]]);
    $this->actingAs(talkPlayer());

    $props = talkProps($this);

    expect($props['speech'])->toBe('這是 魚A。我們 iratay 多數人說它是 oyod；怎麼處理還沒有答案。你知道怎麼處理它嗎？這裡的魚都講完了。')
        ->and($props['fish']['ask'])->toBe('processing_method');
});

it('只有人答「我不知道」、空值與清單外的值、或只有別的部落答過：兩題都沒結果，問分類，不能看誰說的', function () {
    $fish = talkFish(1, '魚B');
    talkAnswers($fish, 'iratay', [[null, '我不知道'], ['', ''], ['?', '尚未紀錄'], ['OYOD', '去魚鱗 ']]);
    talkAnswers($fish, 'yayo', [['oyod', '去魚鱗']]);
    $this->actingAs(talkPlayer());

    $props = talkProps($this);

    expect($props['speech'])->toBe('這是 魚B。我們 iratay 還沒有分類的答案；怎麼處理還沒有答案。你知道它算哪一類嗎？這裡的魚都講完了。')
        ->and($props['fish']['ask'])->toBe('food_category')
        ->and($props['fish']['results'])->toBeNull();
});

it('最後一條（N＝M，含 M＝1）照常講這條魚，句末加「這裡的魚都講完了。」', function () {
    talkFish(1, '魚A');
    talkFish(5, '魚B');
    $this->actingAs(talkPlayer());

    $last = talkProps($this, '?n=2');
    $notLast = talkProps($this, '?n=1');

    expect($last['is_last'])->toBeTrue()
        ->and($last['speech'])->toEndWith('你知道它算哪一類嗎？這裡的魚都講完了。')
        ->and($notLast['is_last'])->toBeFalse()
        ->and($notLast['speech'])->not->toContain('這裡的魚都講完了');
});

it('對話頁帶細節視窗要用的選項清單、部落清單與「我不知道」', function () {
    talkFish(1, '魚A');
    $this->actingAs(talkPlayer());

    $props = talkProps($this);

    expect($props['tribes'])->toBe(config('fish_options.tribes'))
        ->and($props['result_options'])->toBe(FishTribeResponse::islandResultOptions())
        ->and($props['unknown_processing_method'])->toBe('我不知道')
        ->and($props['tribe'])->toBe('iratay');
});
