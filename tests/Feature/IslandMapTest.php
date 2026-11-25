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
it('部落頁每頁 20 條魚卡、依編號排序，可用頁碼取下一頁', function () {
    $fishes = Fish::factory()->count(21)->create();
    $this->actingAs(islandPlayer());

    $this->get('/island/tribes/yayo')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Island/Tribe')
            ->where('tribe', 'yayo')
            ->has('fish', 20)
            ->where('fish.0.id', $fishes[0]->id)
            ->where('fish.19.id', $fishes[19]->id)
            ->where('has_more_pages', true)
            ->where('empty_reason', null));

    $this->get('/island/tribes/yayo?page=2')
        ->assertInertia(fn ($page) => $page
            ->has('fish', 1)
            ->where('fish.0.id', $fishes[20]->id)
            ->where('page', 2)
            ->where('has_more_pages', false));
});

it('魚卡帶編號、魚名、照片網址；魚名是「我不知道」時不當成魚名', function () {
    $named = Fish::factory()->create(['name' => '浪人鰺']);
    Fish::factory()->create(['name' => '我不知道']);
    $this->actingAs(islandPlayer());

    $this->get('/island/tribes/yayo')
        ->assertInertia(fn ($page) => $page
            ->where('fish.0.id', $named->id)
            ->where('fish.0.name', '浪人鰺')
            ->where('fish.0.image_url', $named->display_image_url)
            ->where('fish.1.name', null));
});

it('回答人數只算這個部落的不同帳號；只答叫法、只答分類、只有錄音都算 1 人', function () {
    $fish = Fish::factory()->create();
    $other = Fish::factory()->create();
    [$a, $b, $c, $d] = User::factory()->count(4)->create()->all();
    islandAnswer($a, $fish, 'yayo', ['name' => 'ilek']);
    islandAnswer($b, $fish, 'yayo', ['food_category' => 'oyod']);
    islandAnswer($c, $fish, 'yayo', ['recording_filename' => 'only-voice.webm', 'recording_seconds' => 3]);
    islandAnswer($a, $fish, 'iratay', ['name' => 'ilek']);
    islandAnswer($d, $fish, 'iratay', ['name' => 'ilek']);
    $this->actingAs(islandPlayer());

    $this->get('/island/tribes/yayo')
        ->assertInertia(fn ($page) => $page
            ->where('fish.0.id', $fish->id)
            ->where('fish.0.responder_count', 3)
            ->where('fish.1.id', $other->id)
            ->where('fish.1.responder_count', 0));

    $this->get('/island/tribes/iratay')
        ->assertInertia(fn ($page) => $page->where('fish.0.responder_count', 2));
});

it('訪客的回答也算人數', function () {
    $fish = Fish::factory()->create();
    islandAnswer(IslandCharacter::createGuest('訪客甲', 'yayo'), $fish, 'yayo', ['name' => 'ilek']);
    $this->actingAs(islandPlayer());

    $this->get('/island/tribes/yayo')
        ->assertInertia(fn ($page) => $page->where('fish.0.responder_count', 1));
});

it('軟刪除的魚不出現在魚卡，也不算頁數', function () {
    $kept = Fish::factory()->create();
    $deleted = Fish::factory()->create();
    islandAnswer(User::factory()->create(), $deleted, 'yayo', ['name' => 'ilek']);
    $deleted->delete();
    $this->actingAs(islandPlayer());

    $this->get('/island/tribes/yayo')
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

    foreach (['/island/tribes/yayo', '/island/tribes/yayo?page=2'] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('fish', 0)
                ->where('empty_reason', 'no_fish'));
    }
});

it('魚全部軟刪除時是「目前沒有魚」', function () {
    Fish::factory()->count(2)->create()->each->delete();
    $this->actingAs(islandPlayer());

    $this->get('/island/tribes/yayo')
        ->assertInertia(fn ($page) => $page->has('fish', 0)->where('empty_reason', 'no_fish'));
});

it('有魚但頁碼超過最後一頁是「沒有更多了」', function () {
    Fish::factory()->count(21)->create();
    $this->actingAs(islandPlayer());

    $this->get('/island/tribes/yayo?page=3')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('fish', 0)
            ->where('empty_reason', 'no_more')
            ->where('has_more_pages', false));
});

// AC 3（規格修訂第 1 次）：叫法多數與回答者
function islandNamedAnswers(Fish $fish, string $tribe, array $names): array
{
    return array_map(function (string $name) use ($fish, $tribe) {
        $user = User::factory()->create();
        islandAnswer($user, $fish, $tribe, ['name' => $name]);

        return $user;
    }, $names);
}

function tribeCard(string $tribe = 'yayo'): array
{
    $props = test()->get("/island/tribes/{$tribe}")->assertOk()->inertiaProps();

    return $props['fish'][0];
}

it('填叫法 3 人都同一種寫法：大家叫它那個寫法（大小寫、前後空白算同一種，用最早送出的寫法）', function () {
    $fish = Fish::factory()->create();
    islandNamedAnswers($fish, 'yayo', ['Ilek', ' ilek ', 'ILEK']);
    $this->actingAs(islandPlayer());

    expect(tribeCard()['majority_name'])->toBe('Ilek');
});

it('填叫法不到 3 人：沒有多數', function () {
    $fish = Fish::factory()->create();
    islandNamedAnswers($fish, 'yayo', ['ilek', 'ilek']);
    $this->actingAs(islandPlayer());

    expect(tribeCard())->toMatchArray(['majority_name' => null, 'responder_count' => 2]);
});

it('第一名與第二名平手：沒有多數', function () {
    $fish = Fish::factory()->create();
    islandNamedAnswers($fish, 'yayo', ['ilek', 'ilek', 'savali', 'savali']);
    $this->actingAs(islandPlayer());

    expect(tribeCard()['majority_name'])->toBeNull();
});

it('2 對 1 且填叫法共 3 人：第一名是多數', function () {
    $fish = Fish::factory()->create();
    islandNamedAnswers($fish, 'yayo', ['savali', 'ilek', 'ilek']);
    $this->actingAs(islandPlayer());

    expect(tribeCard()['majority_name'])->toBe('ilek');
});

it('只答分類或只有錄音的人不算進叫法人數；別的部落的叫法不算', function () {
    $fish = Fish::factory()->create();
    islandNamedAnswers($fish, 'yayo', ['ilek', 'ilek']);
    islandAnswer(User::factory()->create(), $fish, 'yayo', ['food_category' => 'oyod']);
    islandAnswer(User::factory()->create(), $fish, 'yayo', ['recording_filename' => 'v.webm', 'recording_seconds' => 2]);
    islandNamedAnswers($fish, 'iratay', ['ilek']);
    $this->actingAs(islandPlayer());

    expect(tribeCard())->toMatchArray(['majority_name' => null, 'responder_count' => 4]);
});

it('回答者列最早回答的前 2 位角色名；沒有魚島角色的回答者名字為空', function () {
    $fish = Fish::factory()->create();
    $first = islandPlayer('yayo', '阿海');
    $amongUser = User::factory()->create(['name' => '帳號名不顯示']);
    $third = islandPlayer('iratay', 'Siyapen');
    islandAnswer($first, $fish, 'yayo', ['name' => 'ilek']);
    islandAnswer($amongUser, $fish, 'yayo', ['food_category' => 'oyod']);
    islandAnswer($third, $fish, 'yayo', ['name' => 'ilek']);
    $this->actingAs(islandPlayer('yayo', '看的人'));

    expect(tribeCard()['first_responders'])->toBe([['name' => '阿海'], ['name' => null]]);
});

it('沒人回答的魚：沒有多數、回答者清單為空', function () {
    Fish::factory()->create();
    $this->actingAs(islandPlayer());

    expect(tribeCard())->toMatchArray(['responder_count' => 0, 'majority_name' => null, 'first_responders' => []]);
});

// AC 7：進度
it('進度：這個部落有人說過的魚條數／未軟刪除的魚總數，不分頁、軟刪除的魚不算', function () {
    $fishes = Fish::factory()->count(22)->create();
    islandAnswer(User::factory()->create(), $fishes[0], 'yayo', ['name' => 'ilek']);
    islandAnswer(User::factory()->create(), $fishes[0], 'yayo', ['food_category' => 'oyod']);
    islandAnswer(User::factory()->create(), $fishes[21], 'yayo', ['recording_filename' => 'v.webm', 'recording_seconds' => 2]);
    islandAnswer(User::factory()->create(), $fishes[5], 'iratay', ['name' => 'ilek']);
    islandAnswer(User::factory()->create(), $fishes[6], 'yayo', ['name' => 'ilek']);
    $fishes[6]->delete();
    $this->actingAs(islandPlayer());

    $this->get('/island/tribes/yayo')
        ->assertInertia(fn ($page) => $page->where('progress', ['answered' => 2, 'total' => 21]));
});

it('進度：沒有人說過時是 0 / Y；沒有魚時 total 為 0', function () {
    $this->actingAs(islandPlayer());
    $this->get('/island/tribes/yayo')
        ->assertInertia(fn ($page) => $page->where('progress', ['answered' => 0, 'total' => 0]));

    Fish::factory()->count(3)->create();
    $this->get('/island/tribes/yayo')
        ->assertInertia(fn ($page) => $page->where('progress', ['answered' => 0, 'total' => 3]));
});
