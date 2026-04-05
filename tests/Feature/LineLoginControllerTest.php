<?php

use App\Models\User;
use App\Services\LineLoginService;
use App\Services\LineUserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'line.login_channel_id'    => 'TEST_CHANNEL_ID',
        'line.login_callback_url'  => 'http://localhost/auth/line/callback',
    ]);
});

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

it('callback_新使用者首次登入建立_User_記錄', function () {
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

    $state = 'test_state_value';

    $this->withSession(['line_login_state' => $state])
        ->get('/auth/line/callback?code=fake_code&state=' . $state);

    $this->assertDatabaseHas('users', [
        'line_user_id' => 'Unewuser123',
        'name'         => '新使用者',
        'source'       => 'line',
        'role'         => 'viewer',
    ]);
});

it('callback_新使用者登入後_Auth_session_已建立', function () {
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

    $state = 'session_state';

    $response = $this->withSession(['line_login_state' => $state])
        ->get('/auth/line/callback?code=fake_code&state=' . $state);

    $response->assertRedirect('/fishs');
    $this->assertAuthenticated();
});

it('callback_state_不符合時拒絕登入', function () {
    $state = 'correct_state';

    $response = $this->withSession(['line_login_state' => $state])
        ->get('/auth/line/callback?code=fake_code&state=wrong_state');

    $response->assertRedirect('/login');
    $this->assertGuest();
});

it('callback_重複登入不會建立重複的_User_且更新名稱', function () {
    User::factory()->lineViewer()->create([
        'line_user_id' => 'Uexisting',
        'name'         => '舊名字',
    ]);

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

    $state = 'existing_state';

    $this->withSession(['line_login_state' => $state])
        ->get('/auth/line/callback?code=fake_code&state=' . $state);

    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseHas('users', ['line_user_id' => 'Uexisting', 'name' => '新名字']);
});
