<?php

use App\Models\Fish;
use App\Models\IslandCharacter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

function postIslandCharacter(array $data): TestResponse
{
    return test()->post('/island/character', $data);
}

/** 模擬同一個瀏覽器還沒拿到登入 cookie：清掉登入狀態與 session。 */
function forgetIslandLogin(): void
{
    Auth::forgetGuards();
    test()->flushSession();
}

function islandCharacterFor(User $user, string $name = '阿海', string $tribe = 'yayo'): IslandCharacter
{
    return IslandCharacter::create(['user_id' => $user->id, 'name' => $name, 'tribe' => $tribe]);
}

// AC 1
it('沒登入、沒有角色的人開 /island 會被導到角色設定頁', function () {
    $this->get('/island')->assertRedirect('/island/character');
});

it('角色設定頁不用登入，帶部落清單與名字上限', function () {
    $this->get('/island/character')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Island/Character')
            ->where('tribes', ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley'])
            ->where('name_max_length', 20)
            ->missing('form_token'));
});

// AC 2
it('沒登入的人送出後得到訪客帳號與角色，並登入導到 /island', function () {
    postIslandCharacter(['name' => '阿海', 'tribe' => 'yayo'])
        ->assertRedirect('/island');

    expect(User::count())->toBe(1);
    $user = User::first();
    expect($user->source)->toBe('island_guest')
        ->and($user->role)->toBe('guest')
        ->and($user->email)->toBeNull()
        ->and($user->password)->toBeNull()
        ->and($user->name)->toBe('阿海');

    expect(IslandCharacter::count())->toBe(1);
    $character = IslandCharacter::first();
    expect($character->user_id)->toBe($user->id)
        ->and($character->name)->toBe('阿海')
        ->and($character->tribe)->toBe('yayo');

    $this->assertAuthenticatedAs($user);
});

it('訪客帳號開 among 要登入的頁面仍是 403', function () {
    $fish = Fish::factory()->create();
    postIslandCharacter(['name' => '阿海', 'tribe' => 'yayo']);

    $this->get("/fish/{$fish->id}")->assertForbidden();
});

it('拿到登入 cookie 前送出兩次會建出兩個訪客帳號，瀏覽器留在後完成的那一個（已知行為，不去重）', function () {
    $data = ['name' => '阿海', 'tribe' => 'yayo'];

    postIslandCharacter($data)->assertRedirect('/island');
    $firstUserId = Auth::id();
    forgetIslandLogin();
    postIslandCharacter($data)->assertRedirect('/island');

    expect(User::count())->toBe(2)
        ->and(IslandCharacter::count())->toBe(2)
        ->and(User::where('source', 'island_guest')->count())->toBe(2)
        ->and(Auth::id())->not->toBe($firstUserId);
});

it('第一次完成後才送第二次（已帶登入狀態）視為已有角色，不新增任何資料', function () {
    $data = ['name' => '阿海', 'tribe' => 'yayo'];

    postIslandCharacter($data)->assertRedirect('/island');
    postIslandCharacter($data)->assertRedirect('/island');

    expect(User::count())->toBe(1)->and(IslandCharacter::count())->toBe(1);
});

// AC 3
it('已登入的帳號沒有角色時送出只多一筆角色，帳號不變', function (string $role, string $source) {
    $user = User::factory()->create(['role' => $role, 'source' => $source, 'name' => 'LINE 顯示名稱']);

    $this->actingAs($user)
        ->post('/island/character', ['name' => '阿海', 'tribe' => 'iraraley'])
        ->assertRedirect('/island');

    expect(User::count())->toBe(1);
    $user->refresh();
    expect($user->role)->toBe($role)
        ->and($user->source)->toBe($source)
        ->and($user->name)->toBe('LINE 顯示名稱');

    expect(IslandCharacter::count())->toBe(1);
    $character = IslandCharacter::first();
    expect($character->user_id)->toBe($user->id)
        ->and($character->name)->toBe('阿海')
        ->and($character->tribe)->toBe('iraraley');
})->with([
    'admin' => ['admin', 'web'],
    'editor' => ['editor', 'line'],
    'viewer' => ['viewer', 'line'],
    'guest' => ['guest', 'line'],
]);

// AC 4
it('已有角色的人開 /island 看到角色名與部落（來自角色，不是帳號名稱）', function () {
    $user = User::factory()->create(['name' => 'LINE 顯示名稱']);
    islandCharacterFor($user, '阿海', 'yayo');

    $this->actingAs($user)->get('/island')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Island/Home')
            ->where('character.name', '阿海')
            ->where('character.tribe', 'yayo'));
});

it('清掉 session、只留記住我的 cookie 再開 /island 仍是同一個帳號與角色', function () {
    $response = postIslandCharacter(['name' => '阿海', 'tribe' => 'yayo']);
    $userId = Auth::id();
    $recaller = Auth::guard()->getRecallerName();
    $rememberCookie = $response->getCookie($recaller, decrypt: true);
    expect($rememberCookie)->not->toBeNull();

    forgetIslandLogin();

    $this->withCookie($recaller, $rememberCookie->getValue())
        ->get('/island')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Island/Home')
            ->where('character.name', '阿海')
            ->where('character.tribe', 'yayo'));
    expect(Auth::id())->toBe($userId);
});

// AC 5
it('已有角色的人開角色設定頁會被導回 /island', function () {
    $user = User::factory()->create();
    islandCharacterFor($user);

    $this->actingAs($user)->get('/island/character')->assertRedirect('/island');
});

it('已有角色的人再次送出，角色不變、不新增任何資料', function () {
    $user = User::factory()->create();
    islandCharacterFor($user, '阿海', 'yayo');

    $this->actingAs($user)
        ->post('/island/character', ['name' => '新名字', 'tribe' => 'iraraley'])
        ->assertRedirect('/island');

    expect(User::count())->toBe(1)->and(IslandCharacter::count())->toBe(1);
    $character = IslandCharacter::first();
    expect($character->name)->toBe('阿海')->and($character->tribe)->toBe('yayo');
});

// AC 6
it('名字或部落不合法時不建帳號、不建角色並顯示錯誤', function (string $name, string $tribe, string $errorField) {
    $this->from('/island/character')
        ->post('/island/character', ['name' => $name, 'tribe' => $tribe])
        ->assertRedirect('/island/character')
        ->assertSessionHasErrors([$errorField]);

    expect(User::count())->toBe(0)->and(IslandCharacter::count())->toBe(0);
    $this->assertGuest();
})->with([
    '名字是空的' => ['', 'yayo', 'name'],
    '名字只有半形與全形空白' => [" \u{3000} ", 'yayo', 'name'],
    '名字 21 個字' => [str_repeat('海', 21), 'yayo', 'name'],
    '部落不在清單裡' => ['阿海', 'taipei', 'tribe'],
]);

it('名字前後的空白（含全形）會被去掉，剛好 20 個字可以通過', function () {
    $name = str_repeat('海', 20);

    postIslandCharacter(['name' => "\u{3000} {$name} ", 'tribe' => 'yayo'])
        ->assertRedirect('/island');

    expect(IslandCharacter::first()->name)->toBe($name);
});

it('沒登入的人一分鐘內第 6 次送出回 429，不建任何資料', function () {
    foreach (range(1, 5) as $ignored) {
        postIslandCharacter(['name' => '', 'tribe' => 'yayo'])->assertRedirect();
    }

    postIslandCharacter(['name' => '阿海', 'tribe' => 'yayo'])->assertStatus(429);

    expect(User::count())->toBe(0)->and(IslandCharacter::count())->toBe(0);
});

it('已有角色的人第 6 次送出也是 429（限流先於導回）', function () {
    $user = User::factory()->create();
    islandCharacterFor($user);

    foreach (range(1, 5) as $ignored) {
        $this->actingAs($user)->post('/island/character', ['name' => '阿海', 'tribe' => 'yayo'])->assertRedirect('/island');
    }

    $this->actingAs($user)->post('/island/character', ['name' => '阿海', 'tribe' => 'yayo'])->assertStatus(429);
});

it('沒登入與已登入的限流分開計算', function () {
    foreach (range(1, 5) as $ignored) {
        postIslandCharacter(['name' => '', 'tribe' => 'yayo']);
    }
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/island/character', ['name' => '阿海', 'tribe' => 'yayo'])
        ->assertRedirect('/island');
});
