<?php

use App\Models\LineUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create();
});

it('index_requires_authentication', function () {
    $response = $this->get('/line-users');
    $response->assertRedirect('/login');
});

it('index_returns_line_users_page', function () {
    LineUser::factory()->count(3)->create();

    $response = $this->actingAs($this->admin)->get('/line-users');

    $response->assertStatus(200);
    $response->assertInertia(
        fn ($page) => $page
        ->component('LineUsers')
        ->has('lineUsers.data', 3)
    );
});

it('update_role_requires_authentication', function () {
    $lineUser = LineUser::factory()->create(['role' => 'viewer']);

    $response = $this->putJson("/line-users/{$lineUser->id}/role", ['role' => 'editor']);

    $response->assertStatus(401);
});

it('update_role_changes_user_role_and_returns_200', function () {
    $lineUser = LineUser::factory()->create(['line_user_id' => 'U123', 'role' => 'viewer']);

    $mockService = Mockery::mock(\App\Contracts\LineUserServiceInterface::class);
    $mockService->shouldReceive('assignRole')
        ->once()
        ->with('U123', 'editor')
        ->andReturn($lineUser->fresh()->fill(['role' => 'editor']));
    $this->app->instance(\App\Contracts\LineUserServiceInterface::class, $mockService);

    $response = $this->actingAs($this->admin)
        ->putJson("/line-users/{$lineUser->id}/role", ['role' => 'editor']);

    $response->assertStatus(200);
    $response->assertJson(['role' => 'editor']);
});

it('update_role_rejects_invalid_role_value', function () {
    $lineUser = LineUser::factory()->create(['role' => 'viewer']);

    $response = $this->actingAs($this->admin)
        ->putJson("/line-users/{$lineUser->id}/role", ['role' => 'superadmin']);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['role']);
});
