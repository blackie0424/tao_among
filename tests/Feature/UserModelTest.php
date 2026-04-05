<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// =====================================================
// source 與 role 欄位
// =====================================================

it('web 使用者預設 source 為 web', function () {
    $user = User::factory()->create();

    expect($user->source)->toBe('web');
});

it('role 欄位可儲存 admin', function () {
    $user = User::factory()->admin()->create();

    expect($user->role)->toBe('admin');
});

it('role 欄位可儲存 editor', function () {
    $user = User::factory()->lineEditor()->create();

    expect($user->role)->toBe('editor');
});

it('role 欄位可儲存 viewer', function () {
    $user = User::factory()->lineViewer()->create();

    expect($user->role)->toBe('viewer');
});

// =====================================================
// isAdmin()
// =====================================================

it('isAdmin 回傳 true 當 role 為 admin', function () {
    $user = User::factory()->admin()->create();

    expect($user->isAdmin())->toBeTrue();
});

it('isAdmin 回傳 false 當 role 為 editor', function () {
    $user = User::factory()->lineEditor()->create();

    expect($user->isAdmin())->toBeFalse();
});

it('isAdmin 回傳 false 當 role 為 viewer', function () {
    $user = User::factory()->lineViewer()->create();

    expect($user->isAdmin())->toBeFalse();
});

// =====================================================
// isEditor()
// =====================================================

it('isEditor 回傳 true 當 role 為 editor', function () {
    $user = User::factory()->lineEditor()->create();

    expect($user->isEditor())->toBeTrue();
});

it('isEditor 回傳 true 當 role 為 admin', function () {
    $user = User::factory()->admin()->create();

    expect($user->isEditor())->toBeTrue();
});

it('isEditor 回傳 false 當 role 為 viewer', function () {
    $user = User::factory()->lineViewer()->create();

    expect($user->isEditor())->toBeFalse();
});

// =====================================================
// LINE 使用者欄位
// =====================================================

it('LINE 使用者有 line_user_id', function () {
    $user = User::factory()->lineViewer()->create([
        'line_user_id' => 'Uabc123',
    ]);

    expect($user->line_user_id)->toBe('Uabc123');
});

it('LINE 使用者 source 為 line', function () {
    $user = User::factory()->lineViewer()->create();

    expect($user->source)->toBe('line');
});

it('web 使用者 email 與 password 存在', function () {
    $user = User::factory()->create();

    expect($user->email)->not->toBeNull();
    expect($user->password)->not->toBeNull();
});

it('LINE 使用者 email 與 password 可為 null', function () {
    $user = User::factory()->lineViewer()->create([
        'email'    => null,
        'password' => null,
    ]);

    expect($user->email)->toBeNull();
    expect($user->password)->toBeNull();
});
