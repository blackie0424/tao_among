<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('以_email_和_password_建立_admin_使用者', function () {
    $this->artisan('admin:create', [
        '--email'    => 'admin@example.com',
        '--password' => 'Secret1234!',
    ])->assertExitCode(0);

    $this->assertDatabaseHas('users', [
        'email'  => 'admin@example.com',
        'source' => 'web',
        'role'   => 'admin',
    ]);
});

it('建立的_admin_密碼已雜湊儲存', function () {
    $this->artisan('admin:create', [
        '--email'    => 'admin@example.com',
        '--password' => 'Secret1234!',
    ]);

    $user = User::where('email', 'admin@example.com')->first();
    expect(\Illuminate\Support\Facades\Hash::check('Secret1234!', $user->password))->toBeTrue();
});

it('email_已存在時顯示錯誤訊息並結束', function () {
    User::factory()->admin()->create(['email' => 'admin@example.com']);

    $this->artisan('admin:create', [
        '--email'    => 'admin@example.com',
        '--password' => 'Secret1234!',
    ])
        ->expectsOutputToContain('已存在')
        ->assertExitCode(1);

    $this->assertDatabaseCount('users', 1);
});

it('預設_name_為_email_前綴', function () {
    $this->artisan('admin:create', [
        '--email'    => 'myname@example.com',
        '--password' => 'Secret1234!',
    ]);

    $this->assertDatabaseHas('users', [
        'email' => 'myname@example.com',
        'name'  => 'myname',
    ]);
});

it('可自訂_name_參數', function () {
    $this->artisan('admin:create', [
        '--email'    => 'admin@example.com',
        '--password' => 'Secret1234!',
        '--name'     => '系統管理員',
    ]);

    $this->assertDatabaseHas('users', [
        'email' => 'admin@example.com',
        'name'  => '系統管理員',
    ]);
});
