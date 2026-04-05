<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

it('admin_帳號可以正常登入', function () {
    $admin = User::factory()->admin()->create([
        'email'    => 'admin@example.com',
        'password' => bcrypt('secret'),
    ]);

    $response = $this->post('/login', [
        'email'    => 'admin@example.com',
        'password' => 'secret',
    ]);

    $response->assertRedirect('/fishs');
    $this->assertAuthenticatedAs($admin);
});

it('LINE_使用者不能透過_web_登入表單登入', function () {
    User::factory()->lineEditor()->create([
        'line_user_id' => 'Ulineedit',
        'email'        => null,
        'password'     => null,
    ]);

    // LINE 使用者沒有 email/password，直接嘗試登入
    $response = $this->post('/login', [
        'email'    => 'notexist@line.com',
        'password' => 'anything',
    ]);

    $this->assertGuest();
});

it('source_line_使用者即使設定了_email_也不能透過_web_表單登入', function () {
    // 邊界情境：LINE 使用者帳號被設定了 email（不應該登入）
    User::factory()->lineViewer()->create([
        'email'        => 'line@example.com',
        'password'     => bcrypt('secret'),
        'line_user_id' => 'Uboundary',
    ]);

    $response = $this->post('/login', [
        'email'    => 'line@example.com',
        'password' => 'secret',
    ]);

    $this->assertGuest();
    $response->assertRedirect();
});

it('帳號不存在時回傳錯誤訊息', function () {
    $response = $this->post('/login', [
        'email'    => 'nobody@example.com',
        'password' => 'wrong',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});
