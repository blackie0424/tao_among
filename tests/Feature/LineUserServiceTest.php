<?php

use App\Models\User;
use App\Services\LineUserService;
use App\Contracts\RichMenuServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->richMenuService = Mockery::mock(RichMenuServiceInterface::class);
    $this->service = new LineUserService($this->richMenuService);
});

it('upsert_creates_new_line_user', function () {
    $user = $this->service->upsert('U123', '田調員甲', 'https://example.com/pic.jpg');

    expect($user->line_user_id)->toBe('U123');
    expect($user->name)->toBe('田調員甲');
    expect($user->picture_url)->toBe('https://example.com/pic.jpg');
    expect($user->role)->toBe('viewer');
    expect($user->source)->toBe('line');
    $this->assertDatabaseHas('users', ['line_user_id' => 'U123', 'source' => 'line']);
});

it('upsert_updates_display_name_if_user_exists', function () {
    User::factory()->lineViewer()->create([
        'line_user_id' => 'U123',
        'name'         => '舊名字',
        'role'         => 'editor',
    ]);

    $user = $this->service->upsert('U123', '新名字', null);

    expect($user->name)->toBe('新名字');
    expect($user->role)->toBe('editor'); // 角色不應被 upsert 覆蓋
    $this->assertDatabaseCount('users', 1);
});

it('assign_role_editor_saves_to_database', function () {
    User::factory()->lineViewer()->create(['line_user_id' => 'U456', 'role' => 'viewer']);

    config(['line.editor_rich_menu_id' => 'editor_menu_id']);
    $this->richMenuService->shouldReceive('linkToUser')
        ->once()
        ->with('U456', 'editor_menu_id');

    $user = $this->service->assignRole('U456', 'editor');

    expect($user->role)->toBe('editor');
    $this->assertDatabaseHas('users', ['line_user_id' => 'U456', 'role' => 'editor']);
});

it('assign_role_viewer_links_to_viewer_menu', function () {
    User::factory()->lineEditor()->create(['line_user_id' => 'U789', 'role' => 'editor']);

    config(['line.viewer_rich_menu_id' => 'viewer_menu_id']);
    $this->richMenuService->shouldReceive('linkToUser')
        ->once()
        ->with('U789', 'viewer_menu_id');

    $user = $this->service->assignRole('U789', 'viewer');

    expect($user->role)->toBe('viewer');
});

it('get_role_returns_viewer_for_unknown_user', function () {
    $role = $this->service->getRole('U_nonexistent');

    expect($role)->toBe('viewer');
});

it('get_role_returns_correct_role_for_existing_user', function () {
    User::factory()->lineEditor()->create(['line_user_id' => 'Udef', 'role' => 'editor']);

    $role = $this->service->getRole('Udef');

    expect($role)->toBe('editor');
});
