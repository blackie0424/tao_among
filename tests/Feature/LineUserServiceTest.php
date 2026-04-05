<?php

use App\Models\LineUser;
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
    expect($user->display_name)->toBe('田調員甲');
    expect($user->picture_url)->toBe('https://example.com/pic.jpg');
    expect($user->role)->toBe('viewer');
    $this->assertDatabaseHas('line_users', ['line_user_id' => 'U123']);
});

it('upsert_updates_display_name_if_user_exists', function () {
    LineUser::factory()->create([
        'line_user_id' => 'U123',
        'display_name' => '舊名字',
        'role' => 'editor',
    ]);

    $user = $this->service->upsert('U123', '新名字', null);

    expect($user->display_name)->toBe('新名字');
    expect($user->role)->toBe('editor'); // 角色不應被 upsert 覆蓋
    $this->assertDatabaseCount('line_users', 1);
});

it('assign_role_editor_saves_to_database', function () {
    LineUser::factory()->create(['line_user_id' => 'U456', 'role' => 'viewer']);

    config(['line.editor_rich_menu_id' => 'editor_menu_id']);
    $this->richMenuService->shouldReceive('linkToUser')
        ->once()
        ->with('U456', 'editor_menu_id');

    $user = $this->service->assignRole('U456', 'editor');

    expect($user->role)->toBe('editor');
    $this->assertDatabaseHas('line_users', ['line_user_id' => 'U456', 'role' => 'editor']);
});

it('assign_role_viewer_unlinks_personal_menu', function () {
    LineUser::factory()->create(['line_user_id' => 'U789', 'role' => 'editor']);

    $this->richMenuService->shouldReceive('unlinkFromUser')
        ->once()
        ->with('U789');

    $user = $this->service->assignRole('U789', 'viewer');

    expect($user->role)->toBe('viewer');
});

it('assign_role_admin_links_editor_menu', function () {
    LineUser::factory()->create(['line_user_id' => 'Uabc', 'role' => 'viewer']);

    config(['line.editor_rich_menu_id' => 'editor_menu_id']);
    $this->richMenuService->shouldReceive('linkToUser')
        ->once()
        ->with('Uabc', 'editor_menu_id');

    $user = $this->service->assignRole('Uabc', 'admin');

    expect($user->role)->toBe('admin');
});

it('get_role_returns_viewer_for_unknown_user', function () {
    $role = $this->service->getRole('U_nonexistent');

    expect($role)->toBe('viewer');
});

it('get_role_returns_correct_role_for_existing_user', function () {
    LineUser::factory()->create(['line_user_id' => 'Udef', 'role' => 'editor']);

    $role = $this->service->getRole('Udef');

    expect($role)->toBe('editor');
});
