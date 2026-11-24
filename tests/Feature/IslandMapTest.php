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
