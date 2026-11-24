<?php

use App\Models\Fish;
use App\Models\IslandCharacter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/** 開角色設定頁，取得頁面帶的一次性表單代碼。 */
function islandFormToken(): string
{
    $token = '';
    test()->get('/island/character')
        ->assertOk()
        ->assertInertia(function ($page) use (&$token) {
            $token = $page->toArray()['props']['form_token'];
        });

    return $token;
}

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

/** 模擬另一個請求正拿著同一個表單代碼在建帳號：佔住那個代碼的鎖。 */
function holdIslandFormTokenLock(string $token, int $seconds): void
{
    expect(Cache::lock("island_form_token:{$token}:lock", $seconds)->get())->toBeTrue();
}

function islandCharacterFor(User $user, string $name = '阿海', string $tribe = 'yayo'): IslandCharacter
{
    return IslandCharacter::create(['user_id' => $user->id, 'name' => $name, 'tribe' => $tribe]);
}

// AC 1
it('沒登入、沒有角色的人開 /island 會被導到角色設定頁', function () {
    $this->get('/island')->assertRedirect('/island/character');
});

it('角色設定頁不用登入，帶部落清單、名字上限與表單代碼', function () {
    $this->get('/island/character')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Island/Character')
            ->where('tribes', ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley'])
            ->where('name_max_length', 20)
            ->has('form_token'));
});

// AC 2
it('沒登入的人送出後得到訪客帳號與角色，並登入導到 /island', function () {
    $token = islandFormToken();

    postIslandCharacter(['name' => '阿海', 'tribe' => 'yayo', 'form_token' => $token])
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
    postIslandCharacter(['name' => '阿海', 'tribe' => 'yayo', 'form_token' => islandFormToken()]);

    $this->get("/fish/{$fish->id}")->assertForbidden();
});

it('同一個表單代碼送出兩次只建一個帳號，兩次都登入成同一個帳號', function () {
    $token = islandFormToken();
    $data = ['name' => '阿海', 'tribe' => 'yayo', 'form_token' => $token];

    postIslandCharacter($data)->assertRedirect('/island');
    $firstUserId = Auth::id();
    forgetIslandLogin();
    postIslandCharacter($data)->assertRedirect('/island');

    expect(User::count())->toBe(1)
        ->and(IslandCharacter::count())->toBe(1)
        ->and(Auth::id())->toBe($firstUserId);
});

it('表單代碼不存在時不建任何資料，回到表單並顯示請再送出一次', function () {
    $this->from('/island/character')
        ->post('/island/character', ['name' => '阿海', 'tribe' => 'yayo', 'form_token' => 'not-a-real-token'])
        ->assertRedirect('/island/character')
        ->assertSessionHasErrors(['form_token' => '請再送出一次']);

    expect(User::count())->toBe(0)->and(IslandCharacter::count())->toBe(0);
    $this->assertGuest();
});

it('表單代碼過期（30 分鐘）後不建任何資料', function () {
    $token = islandFormToken();
    $this->travel(31)->minutes();

    $this->from('/island/character')
        ->post('/island/character', ['name' => '阿海', 'tribe' => 'yayo', 'form_token' => $token])
        ->assertSessionHasErrors(['form_token' => '請再送出一次']);

    expect(User::count())->toBe(0)->and(IslandCharacter::count())->toBe(0);
});

it('已用過的表單代碼不會延長效期：開表單 30 分鐘後一樣失效', function () {
    $token = islandFormToken();
    $data = ['name' => '阿海', 'tribe' => 'yayo', 'form_token' => $token];

    $this->travel(29)->minutes();
    postIslandCharacter($data)->assertRedirect('/island');
    forgetIslandLogin();
    $this->travel(2)->minutes();

    $this->from('/island/character')
        ->post('/island/character', $data)
        ->assertRedirect('/island/character')
        ->assertSessionHasErrors(['form_token' => '請再送出一次']);
    expect(User::count())->toBe(1)->and(IslandCharacter::count())->toBe(1);
    $this->assertGuest();
});

it('同一個代碼正被另一個請求使用時會等它結束，再登入成同一個帳號', function () {
    $token = islandFormToken();
    $data = ['name' => '阿海', 'tribe' => 'yayo', 'form_token' => $token];
    postIslandCharacter($data)->assertRedirect('/island');
    $firstUserId = Auth::id();
    forgetIslandLogin();

    $this->freezeTime();
    Sleep::fake(syncWithCarbon: true);
    // 另一個請求花了 20 秒才結束（遠超過正常的建帳號時間）
    holdIslandFormTokenLock($token, seconds: 20);

    postIslandCharacter($data)->assertRedirect('/island');

    Sleep::assertSleptTimes(80);
    expect(User::count())->toBe(1)
        ->and(IslandCharacter::count())->toBe(1)
        ->and(Auth::id())->toBe($firstUserId);
});

it('等另一個請求等到逾時，不建任何資料並請使用者再送出一次', function () {
    $token = islandFormToken();
    $this->freezeTime();
    Sleep::fake(syncWithCarbon: true);
    holdIslandFormTokenLock($token, seconds: 600);

    $this->from('/island/character')
        ->post('/island/character', ['name' => '阿海', 'tribe' => 'yayo', 'form_token' => $token])
        ->assertRedirect('/island/character')
        ->assertSessionHasErrors(['form_token' => '請再送出一次']);

    expect(User::count())->toBe(0)->and(IslandCharacter::count())->toBe(0);
    $this->assertGuest();
});

it('代碼過期送出失敗後，回到表單拿到新代碼，用新代碼重送成功', function () {
    $oldToken = islandFormToken();
    $this->travel(31)->minutes();
    $this->from('/island/character')
        ->post('/island/character', ['name' => '阿海', 'tribe' => 'yayo', 'form_token' => $oldToken])
        ->assertRedirect('/island/character');

    $newToken = islandFormToken();
    expect($newToken)->not->toBe($oldToken);

    postIslandCharacter(['name' => '阿海', 'tribe' => 'yayo', 'form_token' => $newToken])
        ->assertRedirect('/island');
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
    $response = postIslandCharacter(['name' => '阿海', 'tribe' => 'yayo', 'form_token' => islandFormToken()]);
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
    $token = islandFormToken();

    $this->from('/island/character')
        ->post('/island/character', ['name' => $name, 'tribe' => $tribe, 'form_token' => $token])
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

    postIslandCharacter(['name' => "\u{3000} {$name} ", 'tribe' => 'yayo', 'form_token' => islandFormToken()])
        ->assertRedirect('/island');

    expect(IslandCharacter::first()->name)->toBe($name);
});

it('沒登入的人一分鐘內第 6 次送出回 429，不建任何資料', function () {
    foreach (range(1, 5) as $ignored) {
        postIslandCharacter(['name' => '', 'tribe' => 'yayo', 'form_token' => 'x'])->assertRedirect();
    }

    postIslandCharacter(['name' => '阿海', 'tribe' => 'yayo', 'form_token' => 'x'])->assertStatus(429);

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
        postIslandCharacter(['name' => '', 'tribe' => 'yayo', 'form_token' => 'x']);
    }
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/island/character', ['name' => '阿海', 'tribe' => 'yayo'])
        ->assertRedirect('/island');
});
