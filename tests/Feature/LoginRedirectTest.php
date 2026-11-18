<?php

use App\Models\Fish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'line.login_channel_id' => 'TEST_CHANNEL_ID',
        'line.login_callback_url' => 'http://localhost/auth/line/callback',
    ]);
});

function adminCredentials(): array
{
    User::factory()->admin()->create(['email' => 'admin@example.com', 'password' => 'secret']);

    return ['email' => 'admin@example.com', 'password' => 'secret'];
}

function completeLineLogin(User $user, array $session = [])
{
    Cache::put('line_login_token:tok123', $user->id, now()->addMinutes(5));

    return test()->withSession($session)->get('/auth/line/complete?token=tok123');
}

// ── AC 9：表單登入的 redirect ────────────────────────────────

it('表單登入帶站內 redirect 時回到該頁', function () {
    $this->post('/login?redirect='.urlencode('/fish/5'), adminCredentials())
        ->assertRedirect('/fish/5');
});

it('表單登入的 redirect 不是站內路徑時回 /', function (string $bad) {
    $this->post('/login?redirect='.urlencode($bad), adminCredentials())
        ->assertRedirect('/');
})->with(['//evil.com', 'https://evil.com', '/\\evil.com', 'fish/1']);

it('表單登入帶不合法 redirect 時，即使 session 有舊 intended 也回 /', function (string $bad) {
    $this->withSession(['url.intended' => '/fish/5'])
        ->post('/login?redirect='.urlencode($bad), adminCredentials())
        ->assertRedirect('/');
})->with(['//evil.com', 'https://evil.com', '/\\evil.com']);

it('表單登入的合法 redirect 優先於 session 的 intended', function () {
    $this->withSession(['url.intended' => '/fish/5'])
        ->post('/login?redirect='.urlencode('/fish/9'), adminCredentials())
        ->assertRedirect('/fish/9');
});

it('表單登入沒有 redirect 時採用 session 的 intended', function () {
    $this->withSession(['url.intended' => '/fish/5'])
        ->post('/login', adminCredentials())
        ->assertRedirect('/fish/5');
});

// ── AC 10：直接輸入魚頁網址登入後回到魚頁 ─────────────────────

it('未登入者直接輸入魚頁網址，登入後回到該魚頁', function () {
    $fish = Fish::factory()->create();

    $this->get("/fish/{$fish->id}")->assertRedirect('/login');
    $this->post('/login', adminCredentials())->assertRedirect("/fish/{$fish->id}");
});

// ── AC 9：LINE 登入帶 redirect ───────────────────────────────

it('LINE 登入入口把站內 redirect 存進 session', function () {
    $this->get('/auth/line?redirect='.urlencode('/fish/5'))
        ->assertStatus(302);

    expect(session('url.intended'))->toBe('/fish/5');
});

it('LINE 登入入口不接受站外 redirect', function (string $bad) {
    $this->get('/auth/line?redirect='.urlencode($bad))->assertStatus(302);

    expect(session('url.intended'))->toBeNull();
})->with(['//evil.com', 'https://evil.com', '/\\evil.com']);

it('LINE 登入入口收到不合法 redirect 時，清掉舊的 intended', function (string $bad) {
    $this->withSession(['url.intended' => '/fish/5'])
        ->get('/auth/line?redirect='.urlencode($bad))
        ->assertStatus(302);

    expect(session('url.intended'))->toBeNull();
})->with(['//evil.com', 'https://evil.com', '/\\evil.com']);

it('LINE 登入入口沒有 redirect 時保留舊的 intended', function () {
    $this->withSession(['url.intended' => '/fish/5'])->get('/auth/line')->assertStatus(302);

    expect(session('url.intended'))->toBe('/fish/5');
});

// ── AC 10：LINE 登入完成後回到魚頁 ───────────────────────────

it('LINE 登入完成後，已開通的使用者回到原本的魚頁', function () {
    $viewer = User::factory()->lineViewer()->create();

    completeLineLogin($viewer, ['url.intended' => '/fish/5'])->assertRedirect('/fish/5');
    $this->assertAuthenticatedAs($viewer);
});

it('LINE 登入完成後沒有目標時回 /', function () {
    completeLineLogin(User::factory()->lineViewer()->create())->assertRedirect('/');
});

// ── AC 11：登入後仍是 guest ──────────────────────────────────

it('LINE 登入完成後角色仍是 guest：不進魚頁，回 / 並顯示等待開通', function () {
    $guest = User::factory()->lineGuest()->create();

    completeLineLogin($guest, ['url.intended' => '/fish/5'])
        ->assertRedirect('/')
        ->assertSessionHas('info', '你的帳號還在等待開通');
});

it('已開通的使用者登入後不會看到等待開通', function () {
    completeLineLogin(User::factory()->lineViewer()->create(), ['url.intended' => '/fish/5'])
        ->assertSessionMissing('info');
});
