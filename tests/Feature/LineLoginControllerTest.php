<?php

use App\Models\User;
use App\Services\LineLoginService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'line.login_channel_id'   => 'TEST_CHANNEL_ID',
        'line.login_callback_url' => 'http://localhost/auth/line/callback',
    ]);
});

// ─── redirect ────────────────────────────────────────────────────────────────

it('redirect_回傳_LINE_OAuth_授權網址', function () {
    $response = $this->get('/auth/line');

    $response->assertStatus(302);
    $location = $response->headers->get('Location');
    expect($location)->toContain('access.line.me/oauth2/v2.1/authorize');
    expect($location)->toContain('response_type=code');
    expect($location)->toContain('TEST_CHANNEL_ID');
});

it('redirect_包含_state_防止_CSRF', function () {
    $response = $this->get('/auth/line');

    $location = $response->headers->get('Location');
    expect($location)->not->toBeNull();
    expect($location)->toContain('state=');
});

// ─── callback ────────────────────────────────────────────────────────────────

it('callback_新使用者首次登入建立_User_記錄', function () {
    $state = 'test_state_value';
    Cache::put('line_state:' . $state, true, now()->addMinutes(15));

    $mockService = Mockery::mock(LineLoginService::class);
    $mockService->shouldReceive('getUserProfile')
        ->once()
        ->with('fake_code')
        ->andReturn([
            'userId'      => 'Unewuser123',
            'displayName' => '新使用者',
            'pictureUrl'  => 'https://profile.example.com/pic.jpg',
        ]);
    $this->app->instance(LineLoginService::class, $mockService);

    $this->get('/auth/line/callback?code=fake_code&state=' . $state);

    $this->assertDatabaseHas('users', [
        'line_user_id' => 'Unewuser123',
        'name'         => '新使用者',
        'source'       => 'line',
        'role'         => 'viewer',
    ]);
});

it('callback_成功後導向_complete_端點並帶有_token', function () {
    $state = 'session_state';
    Cache::put('line_state:' . $state, true, now()->addMinutes(15));

    $mockService = Mockery::mock(LineLoginService::class);
    $mockService->shouldReceive('getUserProfile')
        ->once()
        ->with('fake_code')
        ->andReturn([
            'userId'      => 'Usessiontest',
            'displayName' => '會話測試',
            'pictureUrl'  => null,
        ]);
    $this->app->instance(LineLoginService::class, $mockService);

    $response = $this->get('/auth/line/callback?code=fake_code&state=' . $state);

    $response->assertStatus(302);
    expect($response->headers->get('Location'))->toContain('/auth/line/complete?token=');
});

it('callback_state_不符合時拒絕登入', function () {
    // 不放入 Cache，模擬 state 不符
    $response = $this->get('/auth/line/callback?code=fake_code&state=wrong_state');

    $response->assertRedirect('/login');
    $this->assertGuest();
});

it('callback_重複登入不會建立重複的_User_且更新名稱', function () {
    User::factory()->lineViewer()->create([
        'line_user_id' => 'Uexisting',
        'name'         => '舊名字',
    ]);

    $state = 'existing_state';
    Cache::put('line_state:' . $state, true, now()->addMinutes(15));

    $mockService = Mockery::mock(LineLoginService::class);
    $mockService->shouldReceive('getUserProfile')
        ->once()
        ->with('fake_code')
        ->andReturn([
            'userId'      => 'Uexisting',
            'displayName' => '新名字',
            'pictureUrl'  => null,
        ]);
    $this->app->instance(LineLoginService::class, $mockService);

    $this->get('/auth/line/callback?code=fake_code&state=' . $state);

    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseHas('users', ['line_user_id' => 'Uexisting', 'name' => '新名字']);
});

// ─── complete ─────────────────────────────────────────────────────────────────

it('complete_有效_token_登入成功並導向_fishs', function () {
    $user = User::factory()->lineViewer()->create();
    Cache::put('line_login_token:valid_token_123', $user->id, now()->addMinutes(5));

    $response = $this->get('/auth/line/complete?token=valid_token_123');

    $response->assertRedirect('/fishs');
    $this->assertAuthenticatedAs($user);
});

it('complete_若有_intended_url_則導向原始受保護頁面', function () {
    $user = User::factory()->lineEditor()->create();
    Cache::put('line_login_token:intended_token', $user->id, now()->addMinutes(5));

    $this->withSession([
        'url.intended' => '/fish/88/capture-records/batch-create',
    ]);

    $response = $this->get('/auth/line/complete?token=intended_token');

    $response->assertRedirect('/fish/88/capture-records/batch-create');
    $this->assertAuthenticatedAs($user);
});

it('complete_無效_token_拒絕並導向_login', function () {
    $response = $this->get('/auth/line/complete?token=invalid_token');

    $response->assertRedirect('/login');
    $this->assertGuest();
});

it('complete_token_只能使用一次', function () {
    $user = User::factory()->lineViewer()->create();
    Cache::put('line_login_token:one_time_token', $user->id, now()->addMinutes(5));

    $this->get('/auth/line/complete?token=one_time_token');
    $this->app->get('auth')->logout();

    // 第二次使用同一 token
    $response = $this->get('/auth/line/complete?token=one_time_token');
    $response->assertRedirect('/login');
    $this->assertGuest();
});
